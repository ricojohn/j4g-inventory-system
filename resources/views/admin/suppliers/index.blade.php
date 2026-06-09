@extends('layouts.app')

@section('page-title', 'Suppliers')

@section('content')
    <x-ui.page-header title="Suppliers">
        @can('manage suppliers')
            <x-slot:actions>
                <x-ui.button :href="route('admin.suppliers.create')">Add Supplier</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="suppliers-search" placeholder="Search suppliers..." class="w-auto! min-w-48" />
                <x-ui.select id="suppliers-status-filter" class="w-auto! min-w-36">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </x-ui.select>
                <x-ui.select id="suppliers-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="suppliers-table-body" pagination-id="suppliers-pagination">
            <x-slot:head>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th>PO Count</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const destroyUrlBase = @json(url('/admin/suppliers'));
    const statusBadge = (status, label) => {
        const classes = status === 'active'
            ? 'bg-green-100 text-green-800'
            : 'bg-gray-100 text-gray-700';
        return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes}">${escapeHtml(label)}</span>`;
    };

    const table = initAsyncTable({
        tbodyId: 'suppliers-table-body',
        paginationId: 'suppliers-pagination',
        dataUrl: @json(route('admin.suppliers.data')),
        columnCount: 6,
        emptyMessage: 'No suppliers found.',
        getParams: () => ({
            search: document.getElementById('suppliers-search')?.value ?? '',
            status: document.getElementById('suppliers-status-filter')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('suppliers-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((supplier) => `
            <tr>
                <td>${escapeHtml(supplier.name)}</td>
                <td>${escapeHtml(supplier.contact)}</td>
                <td>${escapeHtml(supplier.address)}</td>
                <td>${escapeHtml(supplier.po_count)}</td>
                <td>${statusBadge(supplier.status, supplier.status_label)}</td>
                <td class="space-x-2">
                    <a href="${escapeHtml(supplier.edit_url)}" class="ui-row-action">Edit</a>
                    <button type="button" class="ui-row-action ui-row-action-danger delete-supplier" data-id="${supplier.id}">Delete</button>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('suppliers-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('suppliers-status-filter')?.addEventListener('change', () => table.loadData(1));
    document.getElementById('suppliers-per-page')?.addEventListener('change', () => table.loadData(1));

    document.getElementById('suppliers-table-body')?.addEventListener('click', async (event) => {
        const button = event.target.closest('.delete-supplier');
        if (!button) return;

        if (!confirm('Delete this supplier?')) return;

        try {
            await postData(`${destroyUrlBase}/${button.dataset.id}`, {}, 'DELETE');
            showToast('Supplier deleted.');
            table.loadData(table.getCurrentPage());
        } catch (error) {
            showToast(error.message || 'Unable to delete supplier.', 'error');
        }
    });
});
</script>
@endpush
