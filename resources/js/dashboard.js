import './bootstrap';
import './data-table';
import ApexCharts from 'apexcharts';

const chartInstances = {};

function showChartLoading(container) {
    container.innerHTML = `
        <div class="flex h-64 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-gray-200 border-t-slate-900"></div>
        </div>
    `;
}

function showChartEmpty(container, message = 'No data available.') {
    container.innerHTML = `
        <div class="flex h-64 items-center justify-center text-[13px] text-gray-500">
            ${window.escapeHtml(message)}
        </div>
    `;
}

function showChartError(container, message = 'Unable to load chart data.') {
    container.innerHTML = `
        <div class="flex h-64 items-center justify-center text-[13px] text-red-600">
            ${window.escapeHtml(message)}
        </div>
    `;
}

function seriesHasData(series) {
    if (!Array.isArray(series)) {
        return false;
    }

    return series.some((item) => {
        if (Array.isArray(item)) {
            return item.some((value) => Number(value) > 0);
        }

        if (typeof item === 'object' && item !== null && Array.isArray(item.data)) {
            return item.data.some((value) => Number(value) > 0);
        }

        return Number(item) > 0;
    });
}

async function renderChart(containerId, url, buildOptions) {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }

    showChartLoading(container);

    try {
        const response = await window.axios.get(url);
        const payload = response.data;

        if (chartInstances[containerId]) {
            chartInstances[containerId].destroy();
            chartInstances[containerId] = null;
        }

        const series = payload.series ?? [];
        const categories = payload.categories ?? payload.labels ?? [];

        if (!seriesHasData(series)) {
            showChartEmpty(container);
            return;
        }

        container.innerHTML = '';
        const options = buildOptions(payload);
        const chart = new ApexCharts(container, options);
        chartInstances[containerId] = chart;
        await chart.render();
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'Unable to load chart data.';
        showChartError(container, message);
    }
}

function baseChartOptions() {
    return {
        chart: {
            fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
            toolbar: { show: false },
        },
        dataLabels: { enabled: false },
        legend: {
            fontSize: '12px',
            labels: { colors: '#6b7280' },
        },
        grid: {
            borderColor: '#f3f4f6',
            strokeDashArray: 4,
        },
        tooltip: {
            theme: 'light',
            style: { fontSize: '12px' },
        },
    };
}

function renderStockHealthChart(url) {
    return renderChart('chart-stock-health', url, (payload) => ({
        ...baseChartOptions(),
        chart: { ...baseChartOptions().chart, type: 'donut', height: 280 },
        labels: payload.labels,
        series: payload.series,
        colors: ['#16a34a', '#d97706', '#dc2626'],
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Cells',
                            fontSize: '12px',
                            color: '#6b7280',
                        },
                    },
                },
            },
        },
        stroke: { width: 2, colors: ['#ffffff'] },
    }));
}

function renderMovementTrendChart(url) {
    return renderChart('chart-movement-trend', url, (payload) => ({
        ...baseChartOptions(),
        chart: { ...baseChartOptions().chart, type: 'area', height: 280 },
        series: payload.series,
        xaxis: {
            categories: payload.categories,
            labels: { style: { colors: '#6b7280', fontSize: '11px' } },
        },
        yaxis: {
            labels: { style: { colors: '#6b7280', fontSize: '11px' } },
        },
        colors: ['#16a34a', '#dc2626', '#6b7280'],
        stroke: { curve: 'smooth', width: 2 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.05 },
        },
    }));
}

function renderLowStockByProductChart(url) {
    return renderChart('chart-low-stock-by-product', url, (payload) => ({
        ...baseChartOptions(),
        chart: { ...baseChartOptions().chart, type: 'bar', height: 280, stacked: true },
        series: payload.series,
        xaxis: {
            categories: payload.categories,
            labels: {
                style: { colors: '#6b7280', fontSize: '11px' },
                trim: true,
                rotate: -35,
            },
        },
        yaxis: {
            labels: { style: { colors: '#6b7280', fontSize: '11px' } },
        },
        colors: ['#d97706', '#dc2626'],
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 4,
            },
        },
    }));
}

function renderActiveProductsChart(url) {
    return renderChart('chart-active-products', url, (payload) => ({
        ...baseChartOptions(),
        chart: { ...baseChartOptions().chart, type: 'bar', height: 280 },
        series: payload.series,
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '65%',
            },
        },
        xaxis: {
            categories: payload.categories,
            labels: { style: { colors: '#6b7280', fontSize: '11px' } },
        },
        yaxis: {
            labels: { style: { colors: '#374151', fontSize: '11px' } },
        },
        colors: ['#0f172a'],
    }));
}

export function renderMovementBadge(type) {
    const normalized = String(type).toUpperCase();
    const classes = {
        IN: 'bg-green-100 text-green-800',
        OUT: 'bg-red-100 text-red-800',
        RESERVE: 'bg-blue-100 text-blue-800',
        RELEASE: 'bg-blue-100 text-blue-800',
        DAMAGED: 'bg-gray-200 text-gray-800',
        ADJUSTMENT: 'bg-gray-200 text-gray-800',
    };
    const label = normalized.replace(/_/g, ' ');
    const badgeClasses = classes[normalized] ?? 'bg-gray-100 text-gray-700';

    return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${badgeClasses}">${window.escapeHtml(label)}</span>`;
}

function renderAllCharts(config) {
    renderStockHealthChart(config.routes.stockHealth);
    renderMovementTrendChart(`${config.routes.stockMovementTrend}?days=14`);
    renderLowStockByProductChart(config.routes.lowStockByProduct);
    renderActiveProductsChart(`${config.routes.activeProducts}?days=30`);
}

function initRecentMovementsTable(config) {
    return window.initAsyncTable({
        tbodyId: 'recent-movements-table-body',
        paginationId: 'recent-movements-pagination',
        dataUrl: config.routes.recentMovements,
        columnCount: 7,
        emptyMessage: 'No stock movements yet.',
        getPerPage: () => 10,
        renderRows: (rows) => rows.map((movement) => `
            <tr>
                <td>${window.escapeHtml(movement.created_at)}</td>
                <td>${window.escapeHtml(movement.product_name)}</td>
                <td>${window.escapeHtml(movement.color_name)}</td>
                <td>${window.escapeHtml(movement.size_name)}</td>
                <td>${renderMovementBadge(movement.movement_type)}</td>
                <td>${window.escapeHtml(movement.quantity)}</td>
                <td>${window.escapeHtml(movement.user_name)}</td>
            </tr>
        `).join(''),
    });
}

async function refreshStats(config) {
    const response = await fetch(config.routes.stats, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });
    const payload = await response.json();

    if (!response.ok) {
        return;
    }

    const stats = payload.data;
    const statMap = {
        'total-products': stats.total_products,
        'total-stock': stats.total_stock,
        'total-reserved': stats.total_reserved,
        'total-available': stats.total_available,
        'low-stock-count': stats.low_stock_count,
        'out-of-stock-count': stats.out_of_stock_count,
        'open-orders': stats.open_orders,
        'open-pos': stats.open_pos,
    };

    Object.entries(statMap).forEach(([key, value]) => {
        const card = document.querySelector(`[data-dashboard-stat="${key}"] .stat-value`);
        if (card) {
            card.textContent = value;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const config = window.dashboardConfig;
    if (!config) {
        return;
    }

    const table = initRecentMovementsTable(config);
    renderAllCharts(config);
    table.loadData(1);

    window.addEventListener('inventory:updated', async () => {
        try {
            await refreshStats(config);
            table.loadData(1);
            renderAllCharts(config);
        } catch (_) {
            // Ignore realtime refresh failures.
        }
    });
});
