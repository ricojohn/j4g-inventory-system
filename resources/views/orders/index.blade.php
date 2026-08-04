@extends('layouts.app')

@section('page-title', 'Orders')

@section('content')
    <x-ui.page-header
        eyebrow="Operations"
        title="Orders"
        subtitle="Every customer commitment, next action, and warning in one place."
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                    <a href="{{ route('orders.index') }}" class="rounded bg-brand px-2.5 py-1 text-[12px] font-medium text-white">Table</a>
                    <a href="{{ route('orders.board') }}" class="rounded px-2.5 py-1 text-[12px] font-medium text-gray-600 hover:bg-gray-50">Board</a>
                </div>
                @can('create orders')
                    <x-ui.button :href="route('orders.create')">+ New order</x-ui.button>
                @endcan
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="orders-search" placeholder="Search orders or customers..." class="w-auto! min-w-48" />
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
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Next action</th>
                    <th></th>
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
        return `<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ${classes[status] ?? 'bg-gray-100 text-gray-700'}"><span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>${escapeHtml(label)}</span>`;
    };

    const sourceLine = (order) => {
        if (!order.customer_source_label) return '';
        return `<p class="mt-0.5 text-[11px] text-gray-500">${escapeHtml(order.customer_source_label)}</p>`;
    };

    const table = initAsyncTable({
        tbodyId: 'orders-table-body',
        paginationId: 'orders-pagination',
        dataUrl: config.dataUrl,
        columnCount: 7,
        emptyMessage: 'No customer orders found.',
        getParams: () => ({
            search: document.getElementById('orders-search')?.value ?? '',
            status: document.getElementById('orders-status-filter')?.value ?? '',
            source: document.getElementById('orders-source-filter')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('orders-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((order) => `
            <tr>
                <td>
                    <p class="font-medium text-gray-900">${escapeHtml(order.order_number)}</p>
                    ${sourceLine(order)}
                </td>
                <td>
                    <p class="font-medium text-gray-900">${escapeHtml(order.customer_name)}</p>
                    <p class="mt-0.5 text-[11px] text-gray-500">${escapeHtml(order.customer_contact)}</p>
                </td>
                <td>${escapeHtml(order.due_date ?? '—')}</td>
                <td>${statusBadge(order.status, order.status_label)}</td>
                <td>${escapeHtml(order.payment_status ?? '—')}</td>
                <td>
                    <a href="${escapeHtml(order.show_url)}" class="text-[13px] font-medium text-brand hover:underline">${escapeHtml(order.next_action_label)} →</a>
                </td>
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
