@extends('layouts.app')

@section('page-title', 'Products')

@section('content')
    <x-ui.page-header
        eyebrow="Ledger-based stock"
        title="Inventory"
        subtitle="On hand minus active reservations, across enabled stocked variants."
    >
        @can('create products')
            <x-slot:actions>
                <x-ui.button :href="route('products.create')">+ Add product</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="products-search" placeholder="Search products..." class="w-auto! min-w-48" />
                <x-ui.select id="products-status-filter" class="w-auto! min-w-32">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="all">All</option>
                </x-ui.select>
                <x-ui.select id="products-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="products-table-body" pagination-id="products-pagination">
            <x-slot:head>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Sizes</th>
                    <th>Colors</th>
                    <th>Stock</th>
                    <th>Reserved</th>
                    <th>Low Stock</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
@php
    $tableConfig = [
        'dataUrl' => route('products.data'),
        'permissions' => [
            'canEdit' => auth()->user()?->can('edit products'),
            'canDelete' => auth()->user()?->can('delete products'),
            'canManageInventory' => auth()->user()?->can('view inventory'),
        ],
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.tableConfig = @json($tableConfig);
    const permissions = window.tableConfig.permissions;

    const table = initAsyncTable({
        tbodyId: 'products-table-body',
        paginationId: 'products-pagination',
        dataUrl: window.tableConfig.dataUrl,
        columnCount: 9,
        emptyMessage: 'No products found.',
        getParams: () => ({
            search: document.getElementById('products-search')?.value ?? '',
            status: document.getElementById('products-status-filter')?.value ?? 'active',
        }),
        getPerPage: () => Number(document.getElementById('products-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((product) => `
            <tr>
                <td>${escapeHtml(product.code)}</td>
                <td>${escapeHtml(product.name)}</td>
                <td>${renderStatusPill(product.status)}</td>
                <td>${escapeHtml(product.size_count)}</td>
                <td>${escapeHtml(product.color_count)}</td>
                <td>${escapeHtml(product.total_stock)}</td>
                <td>${escapeHtml(product.total_reserved)}</td>
                <td>${escapeHtml(product.low_stock_count)}</td>
                <td>
                    <div class="flex flex-wrap items-center gap-1">
                        ${permissions.canManageInventory && product.inventory_url ? `
                            <a href="${escapeHtml(product.inventory_url)}" class="ui-row-action">Manage</a>
                        ` : ''}
                        ${permissions.canEdit ? `
                            <a href="${escapeHtml(product.edit_url)}" class="ui-row-action">Edit</a>
                        ` : ''}
                        ${permissions.canDelete ? `
                            <button type="button" class="delete-product ui-row-action ui-row-action-danger" data-id="${product.id}">Delete</button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('products-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('products-status-filter')?.addEventListener('change', () => table.loadData(1));
    document.getElementById('products-per-page')?.addEventListener('change', () => table.loadData(1));

    document.getElementById('products-table-body')?.addEventListener('click', async (event) => {
        const button = event.target.closest('.delete-product');
        if (!button) return;
        if (!confirm('Delete this product?')) return;

        try {
            await postData(`/products/${button.dataset.id}`, {}, 'DELETE');
            showToast('Product deleted.');
            table.loadData(table.getCurrentPage());
        } catch (error) {
            showToast(error.message || 'Unable to delete product.', 'error');
        }
    });
});
</script>
@endpush
