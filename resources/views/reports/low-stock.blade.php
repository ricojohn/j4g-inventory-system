@extends('layouts.app')

@section('page-title', 'Low Stock Report')

@section('content')
    <x-ui.page-header title="Low Stock Report" />

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="low-stock-search" placeholder="Search products..." class="w-auto! min-w-48" />
                <x-ui.select id="low-stock-per-page" class="w-auto! min-w-28">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="low-stock-table-body" pagination-id="low-stock-pagination">
            <x-slot:head>
                <tr>
                    <th>Product</th>
                    <th>Color</th>
                    <th>Size</th>
                    <th>Available</th>
                    <th>Threshold</th>
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
        tbodyId: 'low-stock-table-body',
        paginationId: 'low-stock-pagination',
        dataUrl: @json(route('reports.low-stock.data')),
        columnCount: 6,
        emptyMessage: 'No low stock items.',
        getParams: () => ({
            search: document.getElementById('low-stock-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('low-stock-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((variant) => `
            <tr>
                <td>${escapeHtml(variant.product_name)}</td>
                <td>${escapeHtml(variant.color)}</td>
                <td>${escapeHtml(variant.size_name)}</td>
                <td>${escapeHtml(variant.available_stock)}</td>
                <td>${escapeHtml(variant.reorder_level)}</td>
                <td>${renderStockBadge(variant.status, variant.status_label)}</td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('low-stock-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('low-stock-per-page')?.addEventListener('change', () => table.loadData(1));
});
</script>
@endpush
