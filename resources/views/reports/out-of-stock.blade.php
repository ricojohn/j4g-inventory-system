@extends('layouts.app')

@section('page-title', 'Out of Stock Report')

@section('content')
    <x-ui.page-header title="Out of Stock Report" />

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="out-of-stock-search" placeholder="Search products..." class="w-auto! min-w-48" />
                <x-ui.select id="out-of-stock-per-page" class="w-auto! min-w-28">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="out-of-stock-table-body" pagination-id="out-of-stock-pagination">
            <x-slot:head>
                <tr>
                    <th>Product</th>
                    <th>Color</th>
                    <th>Size</th>
                    <th>Stock</th>
                    <th>Reserved</th>
                    <th>Status</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = initAsyncTable({
        tbodyId: 'out-of-stock-table-body',
        paginationId: 'out-of-stock-pagination',
        dataUrl: @json(route('reports.out-of-stock.data')),
        columnCount: 6,
        emptyMessage: 'No out of stock items.',
        getParams: () => ({
            search: document.getElementById('out-of-stock-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('out-of-stock-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((variant) => `
            <tr>
                <td>${escapeHtml(variant.product_name)}</td>
                <td>${escapeHtml(variant.color)}</td>
                <td>${escapeHtml(variant.size_name)}</td>
                <td>${escapeHtml(variant.stock_quantity)}</td>
                <td>${escapeHtml(variant.reserved_quantity)}</td>
                <td>${renderStockBadge(variant.status, variant.status_label)}</td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('out-of-stock-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('out-of-stock-per-page')?.addEventListener('change', () => table.loadData(1));
});
</script>
@endpush
