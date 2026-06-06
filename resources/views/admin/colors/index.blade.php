@extends('layouts.app')

@section('page-title', 'Colors')

@section('content')
    <x-ui.page-header title="Colors">
        <x-slot:actions>
            <x-ui.button type="button" id="open-color-modal">Add Color</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="colors-search" placeholder="Search colors..." class="w-auto! min-w-48" />
                <x-ui.select id="colors-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="colors-table-body" pagination-id="colors-pagination">
            <x-slot:head>
                <tr>
                    <th>Name</th>
                    <th>Products</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>

    <div id="color-form-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel max-w-md">
            <div class="ui-modal-header">
                <h2 id="color-form-title" class="text-[13px] font-semibold text-gray-900">Add Color</h2>
            </div>
            <div class="ui-modal-body space-y-3">
                <input type="hidden" id="color-edit-id">
                <div>
                    <x-ui.label for="color-form-name">Name</x-ui.label>
                    <x-ui.input id="color-form-name" type="text" maxlength="100" />
                </div>
            </div>
            <div class="ui-modal-footer">
                <x-ui.button type="button" variant="secondary" data-close="color-form-modal">Cancel</x-ui.button>
                <x-ui.button type="button" id="color-form-save">Save</x-ui.button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('color-form-modal');
    const titleEl = document.getElementById('color-form-title');
    const editIdEl = document.getElementById('color-edit-id');
    const nameEl = document.getElementById('color-form-name');

    const table = initAsyncTable({
        tbodyId: 'colors-table-body',
        paginationId: 'colors-pagination',
        dataUrl: @json(route('admin.colors.data')),
        columnCount: 3,
        emptyMessage: 'No colors found.',
        getParams: () => ({
            search: document.getElementById('colors-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('colors-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((color) => `
            <tr>
                <td>${escapeHtml(color.name)}</td>
                <td>${escapeHtml(color.products_count)}</td>
                <td>
                    <div class="flex flex-wrap items-center gap-1">
                        <button type="button" class="edit-color ui-row-action" data-id="${color.id}" data-name="${escapeHtml(color.name)}">Edit</button>
                        <button type="button" class="delete-color ui-row-action ui-row-action-danger" data-id="${color.id}">Delete</button>
                    </div>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('colors-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('colors-per-page')?.addEventListener('change', () => table.loadData(1));

    const closeModal = () => modal.classList.add('hidden');

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    modal?.querySelectorAll('[data-close]').forEach((btn) => btn.addEventListener('click', closeModal));

    document.getElementById('open-color-modal')?.addEventListener('click', () => {
        editIdEl.value = '';
        nameEl.value = '';
        titleEl.textContent = 'Add Color';
        modal.classList.remove('hidden');
        nameEl.focus();
    });

    document.getElementById('colors-table-body')?.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.edit-color');
        if (editBtn) {
            editIdEl.value = editBtn.dataset.id;
            nameEl.value = editBtn.dataset.name;
            titleEl.textContent = 'Edit Color';
            modal.classList.remove('hidden');
            nameEl.focus();
            return;
        }

        const deleteBtn = e.target.closest('.delete-color');
        if (!deleteBtn || !confirm('Delete this color?')) return;

        postData(`/admin/colors/${deleteBtn.dataset.id}`, {}, 'DELETE')
            .then((response) => {
                showToast(response.message || 'Color deleted.');
                table.loadData(table.getCurrentPage());
            })
            .catch((error) => showToast(error.message || 'Unable to delete color.', 'error'));
    });

    document.getElementById('color-form-save')?.addEventListener('click', async () => {
        const id = editIdEl.value;
        const payload = { name: nameEl.value.trim() };

        try {
            const response = id
                ? await postData(`/admin/colors/${id}`, payload, 'PUT')
                : await postData('/admin/colors', payload);
            showToast(response.message || 'Color saved.');
            closeModal();
            table.loadData(id ? table.getCurrentPage() : 1);
        } catch (error) {
            showToast(error.message || 'Unable to save color.', 'error');
        }
    });
});
</script>
@endpush
