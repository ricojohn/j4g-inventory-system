@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    <x-ui.page-header title="Dashboard" />

    @php
        $user = auth()->user();
        $productsHref = $user?->can('view products') ? route('products.index') : null;
        $stockHistoryHref = $user?->can('view stock history') ? route('reports.stock-history') : null;
        $lowStockHref = $user?->can('view low stock report') ? route('reports.low-stock') : null;
        $outOfStockHref = $user?->can('view out of stock report') ? route('reports.out-of-stock') : null;
    @endphp

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.stat-card label="Total Products" :value="$totalProducts" :href="$productsHref" data-dashboard-stat="total-products" />
        <x-ui.stat-card label="Total Stock" :value="$totalStock" :href="$productsHref" data-dashboard-stat="total-stock" />
        <x-ui.stat-card label="Total Reserved" :value="$totalReserved" :href="$stockHistoryHref" data-dashboard-stat="total-reserved" />
        <x-ui.stat-card label="Total Available" :value="$totalAvailable" :href="$productsHref" data-dashboard-stat="total-available" />
        <x-ui.stat-card label="Low Stock Cells" :value="$lowStockCount" accent="warning" :href="$lowStockHref" data-dashboard-stat="low-stock-count" />
        <x-ui.stat-card label="Out of Stock Cells" :value="$outOfStockCount" accent="danger" :href="$outOfStockHref" data-dashboard-stat="out-of-stock-count" />
    </div>

    <x-ui.page-card class="mt-4">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Recent Stock Movements</h2>
        </div>
        <x-ui.async-table tbody-id="recent-movements-table-body" pagination-id="recent-movements-pagination" class="ui-table">
            <x-slot:head>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Color</th>
                    <th>Size</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>User</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = initAsyncTable({
        tbodyId: 'recent-movements-table-body',
        paginationId: 'recent-movements-pagination',
        dataUrl: @json(route('dashboard.recent-movements.data')),
        columnCount: 7,
        emptyMessage: 'No stock movements yet.',
        getPerPage: () => 25,
        renderRows: (rows) => rows.map((movement) => `
            <tr>
                <td>${escapeHtml(movement.created_at)}</td>
                <td>${escapeHtml(movement.product_name)}</td>
                <td>${escapeHtml(movement.color_name)}</td>
                <td>${escapeHtml(movement.size_name)}</td>
                <td>${escapeHtml(movement.movement_type)}</td>
                <td>${escapeHtml(movement.quantity)}</td>
                <td>${escapeHtml(movement.user_name)}</td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    window.addEventListener('inventory:updated', async () => {
        try {
            const response = await fetch(@json(route('dashboard.stats')), {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            const payload = await response.json();
            if (!response.ok) return;

            const stats = payload.data;
            const statMap = {
                'total-products': stats.total_products,
                'total-stock': stats.total_stock,
                'total-reserved': stats.total_reserved,
                'total-available': stats.total_available,
                'low-stock-count': stats.low_stock_count,
                'out-of-stock-count': stats.out_of_stock_count,
            };

            Object.entries(statMap).forEach(([key, value]) => {
                const card = document.querySelector(`[data-dashboard-stat="${key}"] .stat-value`);
                if (card) card.textContent = value;
            });

            table.loadData(1);
        } catch (_) {}
    });
});
</script>
@endpush
