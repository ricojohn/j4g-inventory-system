@extends('layouts.app')

@section('page-title', 'Customer Orders')

@section('content')
    <x-ui.page-header title="Customer Orders">
        @can('create orders')
            <x-slot:actions>
                <x-ui.button :href="route('orders.create')">New Order</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="orders-search" placeholder="Search orders..." class="w-auto! min-w-48" />
                <x-ui.select id="orders-status-filter" class="w-auto! min-w-40">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="reserved">Reserved</option>
                    <option value="partially_reserved">Partially Reserved</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="cancelled">Cancelled</option>
                </x-ui.select>
                <x-ui.select id="orders-source-filter" class="w-auto! min-w-36">
                    <option value="">All Sources</option>
                    <option value="facebook">Facebook</option>
                    <option value="instagram">Instagram</option>
                    <option value="viber">Viber</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="walk_in">Walk-in</option>
                    <option value="referral">Referral</option>
                    <option value="other">Other</option>
                </x-ui.select>
                <x-ui.select id="orders-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="orders-table-body" pagination-id="orders-pagination">
            <x-slot:head>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Source</th>
                    <th>Items</th>
                    <th>PO</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
@php
    $tableConfig = ['dataUrl' => route('orders.data')];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($tableConfig);

    const statusBadge = (status, label) => {
        const classes = {
            pending: 'bg-gray-100 text-gray-700',
            reserved: 'bg-blue-100 text-blue-800',
            partially_reserved: 'bg-amber-100 text-amber-800',
            fulfilled: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800',
        };
        return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes[status] ?? 'bg-gray-100 text-gray-700'}">${escapeHtml(label)}</span>`;
    };

    const poBadge = (order) => {
        if (!order.po_number) return '—';
        const classes = {
            draft: 'bg-gray-100 text-gray-700',
            sent: 'bg-blue-100 text-blue-800',
            partially_received: 'bg-amber-100 text-amber-800',
            received: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800',
        };
        const badgeClass = classes[order.po_status] ?? 'bg-gray-100 text-gray-700';
        return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${badgeClass}">${escapeHtml(order.po_number)}</span>`;
    };

    const sourceBadge = (order) => {
        if (!order.customer_source_label) return '—';
        return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${order.customer_source_badge_color}">${escapeHtml(order.customer_source_icon ?? '')} ${escapeHtml(order.customer_source_label)}</span>`;
    };

    const table = initAsyncTable({
        tbodyId: 'orders-table-body',
        paginationId: 'orders-pagination',
        dataUrl: config.dataUrl,
        columnCount: 9,
        emptyMessage: 'No customer orders found.',
        getParams: () => ({
            search: document.getElementById('orders-search')?.value ?? '',
            status: document.getElementById('orders-status-filter')?.value ?? '',
            source: document.getElementById('orders-source-filter')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('orders-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((order) => `
            <tr>
                <td>${escapeHtml(order.order_number)}</td>
                <td>${escapeHtml(order.customer_name)}</td>
                <td>${escapeHtml(order.customer_contact)}</td>
                <td>${sourceBadge(order)}</td>
                <td>${escapeHtml(order.item_count)}</td>
                <td>${poBadge(order)}</td>
                <td>${statusBadge(order.status, order.status_label)}</td>
                <td>${escapeHtml(order.created_at)}</td>
                <td><a href="${escapeHtml(order.show_url)}" class="ui-row-action">View</a></td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('orders-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('orders-status-filter')?.addEventListener('change', () => table.loadData(1));
    document.getElementById('orders-source-filter')?.addEventListener('change', () => table.loadData(1));
    document.getElementById('orders-per-page')?.addEventListener('change', () => table.loadData(1));
});
</script>
@endpush
