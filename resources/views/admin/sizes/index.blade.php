@extends('layouts.app')

@section('page-title', 'Sizes')

@section('content')
    <x-ui.page-header title="Sizes">
        <x-slot:actions>
            <x-ui.button type="button" id="open-size-modal">Add Size</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="sizes-search" placeholder="Search sizes..." class="!w-auto min-w-48" />
                <x-ui.select id="sizes-per-page" class="!w-auto min-w-28">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="sizes-table-body" pagination-id="sizes-pagination">
            <x-slot:head>
                <tr>
                    <th>Name</th>
                    <th>Sort Order</th>
                    <th>Categories</th>
                    <th>Variants</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>

    <div id="size-modal" class="ui-modal-overlay hidden">
        <div class="ui-modal-panel max-w-lg">
            <div class="ui-modal-header">
                <h2 id="size-modal-title" class="text-[13px] font-semibold text-gray-900">Add Size</h2>
                <p class="mt-0.5 text-[13px] text-gray-500">Create or edit a size name in the global catalog.</p>
            </div>
            <form id="size-form">
                <div class="ui-modal-body space-y-4">
                    <input type="hidden" id="size-id">
                    <div>
                        <x-ui.label for="size-name">Name</x-ui.label>
                        <x-ui.input id="size-name" type="text" required />
                    </div>
                    <div>
                        <x-ui.label for="size-sort-order">Sort Order</x-ui.label>
                        <x-ui.input id="size-sort-order" type="number" min="0" />
                        <p class="mt-1 text-[11px] text-gray-500">Leave blank to append at the end.</p>
                    </div>
                </div>
                <div class="ui-modal-footer">
                    <x-ui.button variant="secondary" type="button" id="close-size-modal">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save</x-ui.button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = initAsyncTable({
        tbodyId: 'sizes-table-body',
        paginationId: 'sizes-pagination',
        dataUrl: @json(route('admin.sizes.data')),
        columnCount: 5,
        emptyMessage: 'No sizes found.',
        getParams: () => ({
            search: document.getElementById('sizes-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('sizes-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((size) => `
            <tr>
                <td>${escapeHtml(size.name)}</td>
                <td>${escapeHtml(size.sort_order)}</td>
                <td>${escapeHtml(size.categories.map((category) => category.name).join(', '))}</td>
                <td>${escapeHtml(size.variant_count)}</td>
                <td>
                    <div class="flex items-center gap-1">
                        <button type="button" class="edit-size ui-row-action" data-id="${size.id}">Edit</button>
                        <button type="button" class="delete-size ui-row-action text-red-600" data-id="${size.id}" data-name="${escapeHtml(size.name)}" ${size.variant_count > 0 ? 'disabled title="Size is in use by variants"' : ''}>Delete</button>
                    </div>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('sizes-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('sizes-per-page')?.addEventListener('change', () => table.loadData(1));

    const modal = document.getElementById('size-modal');
    const form = document.getElementById('size-form');
    const tableBody = document.getElementById('sizes-table-body');
    const title = document.getElementById('size-modal-title');

    const showModal = () => modal?.classList.remove('hidden');
    const hideModal = () => modal?.classList.add('hidden');

    document.getElementById('open-size-modal')?.addEventListener('click', () => {
        form?.reset();
        document.getElementById('size-id').value = '';
        title.textContent = 'Add Size';
        showModal();
    });

    document.getElementById('close-size-modal')?.addEventListener('click', hideModal);

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const id = document.getElementById('size-id').value;
        const sortOrderValue = document.getElementById('size-sort-order').value;
        const payload = {
            name: document.getElementById('size-name').value,
        };

        if (sortOrderValue !== '') {
            payload.sort_order = Number(sortOrderValue);
        }

        try {
            const url = id ? `/admin/sizes/${id}` : '/admin/sizes';
            const method = id ? 'PUT' : 'POST';
            await postData(url, payload, method);
            showToast('Size saved successfully.');
            hideModal();
            table.loadData(table.getCurrentPage());
        } catch (error) {
            showToast(error.message || 'Unable to save size.', 'error');
        }
    });

    tableBody?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.edit-size');
        if (editButton) {
            try {
                const response = await fetch(`/admin/sizes/${editButton.dataset.id}/json`, {
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw payload;
                }

                const size = payload.data;
                document.getElementById('size-id').value = size.id;
                document.getElementById('size-name').value = size.name;
                document.getElementById('size-sort-order').value = size.sort_order ?? '';
                title.textContent = 'Edit Size';
                showModal();
            } catch (error) {
                showToast(error.message || 'Unable to load size.', 'error');
            }

            return;
        }

        const deleteButton = event.target.closest('.delete-size');
        if (!deleteButton || deleteButton.disabled) {
            return;
        }

        if (!confirm(`Delete size "${deleteButton.dataset.name}"?`)) {
            return;
        }

        try {
            await postData(`/admin/sizes/${deleteButton.dataset.id}`, {}, 'DELETE');
            showToast('Size deleted.');
            table.loadData(table.getCurrentPage());
        } catch (error) {
            showToast(error.message || 'Unable to delete size.', 'error');
        }
    });
});
</script>
@endpush
