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
                <x-ui.input type="search" id="sizes-search" placeholder="Search sizes..." class="w-auto! min-w-48" />
                <x-ui.select id="sizes-per-page" class="w-auto! min-w-28">
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
                    <th>Products</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>

    <div id="size-form-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel max-w-md">
            <div class="ui-modal-header">
                <h2 id="size-form-title" class="text-[13px] font-semibold text-gray-900">Add Size</h2>
            </div>
            <div class="ui-modal-body space-y-3">
                <input type="hidden" id="size-edit-id">
                <div>
                    <x-ui.label for="size-form-name">Name</x-ui.label>
                    <x-ui.input id="size-form-name" type="text" maxlength="50" />
                </div>
            </div>
            <div class="ui-modal-footer">
                <x-ui.button type="button" variant="secondary" data-close="size-form-modal">Cancel</x-ui.button>
                <x-ui.button type="button" id="size-form-save">Save</x-ui.button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('size-form-modal');
    const titleEl = document.getElementById('size-form-title');
    const editIdEl = document.getElementById('size-edit-id');
    const nameEl = document.getElementById('size-form-name');

    const table = initAsyncTable({
        tbodyId: 'sizes-table-body',
        paginationId: 'sizes-pagination',
        dataUrl: @json(route('admin.sizes.data')),
        columnCount: 3,
        emptyMessage: 'No sizes found.',
        getParams: () => ({
            search: document.getElementById('sizes-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('sizes-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((size) => `
            <tr>
                <td>${escapeHtml(size.name)}</td>
                <td>${escapeHtml(size.products_count)}</td>
                <td>
                    <div class="flex flex-wrap items-center gap-1">
                        <button type="button" class="edit-size ui-row-action" data-id="${size.id}" data-name="${escapeHtml(size.name)}">Edit</button>
                        <button type="button" class="delete-size ui-row-action ui-row-action-danger" data-id="${size.id}">Delete</button>
                    </div>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('sizes-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('sizes-per-page')?.addEventListener('change', () => table.loadData(1));

    const closeModal = () => modal.classList.add('hidden');

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    modal?.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', closeModal));

    document.getElementById('open-size-modal')?.addEventListener('click', () => {
        editIdEl.value = '';
        nameEl.value = '';
        titleEl.textContent = 'Add Size';
        modal.classList.remove('hidden');
        nameEl.focus();
    });

    document.getElementById('sizes-table-body')?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-size');
        if (editBtn) {
            editIdEl.value = editBtn.dataset.id;
            nameEl.value = editBtn.dataset.name;
            titleEl.textContent = 'Edit Size';
            modal.classList.remove('hidden');
            nameEl.focus();
            return;
        }

        const deleteBtn = e.target.closest('.delete-size');
        if (!deleteBtn || !confirm('Delete this size?')) return;

        postData(`/admin/sizes/${deleteBtn.dataset.id}`, {}, 'DELETE')
            .then((response) => {
                showToast(response.message || 'Size deleted.');
                table.loadData(table.getCurrentPage());
            })
            .catch((error) => showToast(error.message || 'Unable to delete size.', 'error'));
    });

    document.getElementById('size-form-save')?.addEventListener('click', async () => {
        const id = editIdEl.value;
        const payload = { name: nameEl.value.trim() };

        try {
            const response = id
                ? await postData(`/admin/sizes/${id}`, payload, 'PUT')
                : await postData('/admin/sizes', payload);
            showToast(response.message || 'Size saved.');
            closeModal();
            table.loadData(id ? table.getCurrentPage() : 1);
        } catch (error) {
            showToast(error.message || 'Unable to save size.', 'error');
        }
    });
});
</script>
@endpush
