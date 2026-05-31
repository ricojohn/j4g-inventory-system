@extends('layouts.app')

@section('page-title', 'Categories')

@section('content')
    <x-ui.page-header title="Categories">
        @can('create categories')
            <x-slot:actions>
                <x-ui.button type="button" id="open-category-modal">Add Category</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="categories-search" placeholder="Search categories..." class="!w-auto min-w-48" />
                <x-ui.select id="categories-per-page" class="!w-auto min-w-28">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="categories-table-body" pagination-id="categories-pagination">
            <x-slot:head>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Low Stock Threshold</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>

    @canany(['create categories', 'edit categories'])
        <div id="category-modal" class="ui-modal-overlay hidden">
            <div class="ui-modal-panel max-w-lg">
                <div class="ui-modal-header">
                    <h2 id="category-modal-title" class="text-[13px] font-semibold text-gray-900">Add Category</h2>
                    <p class="mt-0.5 text-[13px] text-gray-500">Configure category name, code, and stock settings.</p>
                </div>
                <form id="category-form">
                    <div class="ui-modal-body space-y-4">
                        <input type="hidden" id="category-id">
                        <div>
                            <x-ui.label for="category-name">Name</x-ui.label>
                            <x-ui.input id="category-name" type="text" required />
                        </div>
                        <div>
                            <x-ui.label for="category-code">Code</x-ui.label>
                            <x-ui.input id="category-code" type="text" required />
                        </div>
                        <div>
                            <x-ui.label for="category-threshold">Low Stock Threshold</x-ui.label>
                            <x-ui.input id="category-threshold" type="number" min="0" required />
                        </div>
                        <div>
                            <x-ui.label for="category-status">Status</x-ui.label>
                            <x-ui.select id="category-status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </x-ui.select>
                        </div>
                    </div>
                    <div class="ui-modal-footer">
                        <x-ui.button variant="secondary" type="button" id="close-category-modal">Cancel</x-ui.button>
                        <x-ui.button type="submit">Save</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endcanany

    @can('edit categories')
        <div id="category-sizes-modal" class="ui-modal-overlay hidden">
            <div class="ui-modal-panel max-w-lg">
                <div class="ui-modal-header">
                    <h2 id="category-sizes-modal-title" class="text-[13px] font-semibold text-gray-900">Manage Sizes</h2>
                    <p id="category-sizes-modal-subtitle" class="mt-0.5 text-[13px] text-gray-500"></p>
                </div>
                <form id="category-sizes-form">
                    <div class="ui-modal-body space-y-4">
                        <input type="hidden" id="category-sizes-id">
                        <div>
                            <x-ui.label>Assigned sizes</x-ui.label>
                            <div id="category-sizes-assigned" class="mt-1 space-y-1"></div>
                            <p id="category-sizes-empty" class="mt-1 hidden text-[13px] text-gray-500">No sizes assigned yet.</p>
                        </div>
                        <div>
                            <x-ui.label for="category-sizes-add">Add size</x-ui.label>
                            <div class="mt-1 flex gap-2">
                                <x-ui.select id="category-sizes-add" class="flex-1">
                                    <option value="">Select a size...</option>
                                </x-ui.select>
                                <x-ui.button type="button" variant="secondary" id="category-sizes-add-btn">Add</x-ui.button>
                            </div>
                        </div>
                        @can('manage sizes')
                            <p class="text-[13px] text-gray-500">
                                Need a new size name?
                                <a href="{{ route('admin.sizes.index') }}" class="font-medium text-gray-900 underline">Create it on the Sizes page</a>
                                first, then assign it here.
                            </p>
                        @endcan
                    </div>
                    <div class="ui-modal-footer">
                        <x-ui.button variant="secondary" type="button" id="close-category-sizes-modal">Cancel</x-ui.button>
                        <x-ui.button type="submit">Save Sizes</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
@php
    $tableConfig = [
        'dataUrl' => route('categories.data'),
        'permissions' => [
            'canEdit' => auth()->user()?->can('edit categories'),
            'canDelete' => auth()->user()?->can('delete categories'),
        ],
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.tableConfig = @json($tableConfig);

    const permissions = window.tableConfig.permissions;

    const table = initAsyncTable({
        tbodyId: 'categories-table-body',
        paginationId: 'categories-pagination',
        dataUrl: window.tableConfig.dataUrl,
        columnCount: 5,
        emptyMessage: 'No categories found.',
        getParams: () => ({
            search: document.getElementById('categories-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('categories-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((category) => `
            <tr>
                <td>${escapeHtml(category.name)}</td>
                <td>${escapeHtml(category.code)}</td>
                <td>${escapeHtml(category.low_stock_threshold)}</td>
                <td>${renderStatusPill(category.status)}</td>
                <td>
                    <div class="flex items-center gap-1">
                        ${permissions.canEdit ? `
                            <button type="button" class="edit-category ui-row-action" data-id="${category.id}">Edit</button>
                        ` : ''}
                        ${(permissions.canEdit || permissions.canDelete) ? `
                            <select class="category-more-actions ui-row-more" data-id="${category.id}" data-name="${escapeHtml(category.name)}" aria-label="More actions">
                                <option value="">More</option>
                                ${permissions.canEdit ? '<option value="sizes">Manage Sizes</option>' : ''}
                                ${permissions.canDelete ? '<option value="delete">Delete</option>' : ''}
                            </select>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('categories-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('categories-per-page')?.addEventListener('change', () => table.loadData(1));

    const modal = document.getElementById('category-modal');
    const form = document.getElementById('category-form');
    const sizesModal = document.getElementById('category-sizes-modal');
    const sizesForm = document.getElementById('category-sizes-form');
    const sizesAssignedEl = document.getElementById('category-sizes-assigned');
    const sizesEmptyEl = document.getElementById('category-sizes-empty');
    const sizesAddSelect = document.getElementById('category-sizes-add');
    const sizesAddBtn = document.getElementById('category-sizes-add-btn');
    const sizesModalTitle = document.getElementById('category-sizes-modal-title');
    const sizesModalSubtitle = document.getElementById('category-sizes-modal-subtitle');
    const closeSizesModalBtn = document.getElementById('close-category-sizes-modal');
    const tableBody = document.getElementById('categories-table-body');

    let assignedSizes = [];
    let availableSizes = [];

    const showSizesModal = () => sizesModal?.classList.remove('hidden');
    const hideSizesModal = () => sizesModal?.classList.add('hidden');

    function renderAssignedSizesList() {
        if (!sizesAssignedEl || !sizesEmptyEl) {
            return;
        }

        sizesAssignedEl.innerHTML = '';

        if (assignedSizes.length === 0) {
            sizesEmptyEl.classList.remove('hidden');
            return;
        }

        sizesEmptyEl.classList.add('hidden');

        assignedSizes.forEach((size) => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between rounded-md border border-gray-200 px-2 py-1.5 text-[13px]';
            row.dataset.sizeId = String(size.id);

            const name = document.createElement('span');
            name.textContent = size.name;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'ui-row-action text-red-600';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => removeAssignedSize(size.id));

            row.appendChild(name);
            row.appendChild(removeBtn);
            sizesAssignedEl.appendChild(row);
        });
    }

    function renderAvailableSizesSelect() {
        if (!sizesAddSelect) {
            return;
        }

        sizesAddSelect.innerHTML = '<option value="">Select a size...</option>';

        availableSizes.forEach((size) => {
            const option = document.createElement('option');
            option.value = String(size.id);
            option.textContent = size.name;
            sizesAddSelect.appendChild(option);
        });
    }

    function addAssignedSize(sizeId) {
        const id = Number(sizeId);
        const size = availableSizes.find((entry) => entry.id === id);

        if (!size) {
            return;
        }

        assignedSizes.push(size);
        assignedSizes.sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name));
        availableSizes = availableSizes.filter((entry) => entry.id !== id);

        renderAssignedSizesList();
        renderAvailableSizesSelect();
        sizesAddSelect.value = '';
    }

    function removeAssignedSize(sizeId) {
        const id = Number(sizeId);
        const size = assignedSizes.find((entry) => entry.id === id);

        if (!size) {
            return;
        }

        assignedSizes = assignedSizes.filter((entry) => entry.id !== id);
        availableSizes.push(size);
        availableSizes.sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name));

        renderAssignedSizesList();
        renderAvailableSizesSelect();
    }

    async function openCategorySizes(categoryId, categoryName) {
        document.getElementById('category-sizes-id').value = categoryId;
        sizesModalTitle.textContent = 'Manage Sizes';
        sizesModalSubtitle.textContent = categoryName;
        assignedSizes = [];
        availableSizes = [];
        renderAssignedSizesList();
        renderAvailableSizesSelect();
        showSizesModal();

        try {
            const response = await fetch(`/categories/${categoryId}/sizes`, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            assignedSizes = payload.assigned ?? [];
            availableSizes = payload.available ?? [];
            renderAssignedSizesList();
            renderAvailableSizesSelect();
        } catch (error) {
            hideSizesModal();
            showToast(error.message || 'Unable to load category sizes.', 'error');
        }
    }

    if (modal && form) {
        const openBtn = document.getElementById('open-category-modal');
        const closeBtn = document.getElementById('close-category-modal');
        const title = document.getElementById('category-modal-title');

        const showModal = () => modal.classList.remove('hidden');
        const hideModal = () => modal.classList.add('hidden');

        openBtn?.addEventListener('click', () => {
            form.reset();
            document.getElementById('category-id').value = '';
            title.textContent = 'Add Category';
            showModal();
        });

        closeBtn?.addEventListener('click', hideModal);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = document.getElementById('category-id').value;
            const payload = {
                name: document.getElementById('category-name').value,
                code: document.getElementById('category-code').value,
                low_stock_threshold: Number(document.getElementById('category-threshold').value),
                status: document.getElementById('category-status').value,
            };

            try {
                const url = id ? `/categories/${id}` : '/categories';
                const method = id ? 'PUT' : 'POST';
                await postData(url, payload, method);
                showToast('Category saved successfully.');
                hideModal();
                table.loadData(table.getCurrentPage());
            } catch (error) {
                console.error(error);
                showToast(error.message || 'Unable to save category.', 'error');
            }
        });
    }

    tableBody?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.edit-category');
        if (!editButton) {
            return;
        }

        try {
            const response = await fetch(`/categories/${editButton.dataset.id}/json`, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            const category = payload.data;
            document.getElementById('category-id').value = category.id;
            document.getElementById('category-name').value = category.name;
            document.getElementById('category-code').value = category.code;
            document.getElementById('category-threshold').value = category.low_stock_threshold;
            document.getElementById('category-status').value = category.status;
            document.getElementById('category-modal-title').textContent = 'Edit Category';
            modal?.classList.remove('hidden');
        } catch (error) {
            showToast(error.message || 'Unable to load category.', 'error');
        }
    });

    tableBody?.addEventListener('change', async (event) => {
        const select = event.target.closest('.category-more-actions');
        if (!select || !select.value) {
            return;
        }

        const categoryId = select.dataset.id;
        const categoryName = select.dataset.name;
        const action = select.value;
        select.value = '';

        if (action === 'delete') {
            if (!confirm('Delete this category?')) {
                return;
            }

            try {
                await postData(`/categories/${categoryId}`, {}, 'DELETE');
                showToast('Category deleted.');
                table.loadData(table.getCurrentPage());
            } catch (error) {
                showToast(error.message || 'Unable to delete category.', 'error');
            }

            return;
        }

        if (action === 'sizes') {
            await openCategorySizes(categoryId, categoryName);
        }
    });

    closeSizesModalBtn?.addEventListener('click', hideSizesModal);

    sizesAddBtn?.addEventListener('click', () => {
        if (!sizesAddSelect?.value) {
            showToast('Select a size to add.', 'error');
            return;
        }

        addAssignedSize(sizesAddSelect.value);
    });

    sizesForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const categoryId = document.getElementById('category-sizes-id').value;
        const sizeIds = assignedSizes.map((size) => size.id);

        try {
            await postData(`/categories/${categoryId}/sizes`, { size_ids: sizeIds }, 'PUT');
            showToast('Category sizes updated successfully.');
            hideSizesModal();
        } catch (error) {
            showToast(error.message || 'Unable to save category sizes.', 'error');
        }
    });
});
</script>
@endpush
