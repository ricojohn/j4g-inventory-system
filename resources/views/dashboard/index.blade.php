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
        <x-ui.stat-card label="Total Products" icon="products" :value="$totalProducts" :href="$productsHref" data-dashboard-stat="total-products" />
        <x-ui.stat-card label="Total Stock" icon="stock" :value="$totalStock" :href="$productsHref" data-dashboard-stat="total-stock" />
        <x-ui.stat-card label="Total Reserved" icon="reserved" :value="$totalReserved" :href="$stockHistoryHref" data-dashboard-stat="total-reserved" />
        <x-ui.stat-card label="Total Available" icon="available" :value="$totalAvailable" :href="$productsHref" data-dashboard-stat="total-available" />
        <x-ui.stat-card label="Low Stock Cells" icon="low-stock" :value="$lowStockCount" accent="warning" :href="$lowStockHref" data-dashboard-stat="low-stock-count" />
        <x-ui.stat-card label="Out of Stock Cells" icon="out-of-stock" :value="$outOfStockCount" accent="danger" :href="$outOfStockHref" data-dashboard-stat="out-of-stock-count" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Stock Health</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Distribution of inventory cell status</p>
            </div>
            <div id="chart-stock-health" class="min-h-64 p-4"></div>
        </x-ui.page-card>

        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Stock Movement Trend</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Last 14 days of stock in, out, and damaged</p>
            </div>
            <div id="chart-movement-trend" class="min-h-64 p-4"></div>
        </x-ui.page-card>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Low Stock by Product</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Top 10 products with stock issues</p>
            </div>
            <div id="chart-low-stock-by-product" class="min-h-64 p-4"></div>
        </x-ui.page-card>

        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Most Active Products</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Top 10 products by movement count (30 days)</p>
            </div>
            <div id="chart-active-products" class="min-h-64 p-4"></div>
        </x-ui.page-card>
    </div>

    <x-ui.page-card class="mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
            <div>
                <h2 class="text-[13px] font-semibold text-gray-900">Recent Stock Movements</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Latest inventory activity across all products</p>
            </div>
            @can('view stock history')
                <x-ui.button variant="secondary" :href="route('reports.stock-history')">View All Stock History</x-ui.button>
            @endcan
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
@php
    $dashboardConfig = [
        'routes' => [
            'stats' => route('dashboard.stats'),
            'stockHealth' => route('dashboard.stock-health'),
            'stockMovementTrend' => route('dashboard.stock-movement-trend'),
            'lowStockByProduct' => route('dashboard.low-stock-by-product'),
            'activeProducts' => route('dashboard.active-products'),
            'recentMovements' => route('dashboard.recent-movements.data'),
        ],
    ];
@endphp
<script>
    window.dashboardConfig = @json($dashboardConfig);
</script>
@vite('resources/js/dashboard.js')
@endpush
