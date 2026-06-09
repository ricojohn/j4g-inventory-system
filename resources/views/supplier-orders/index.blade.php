@extends('layouts.app')

@section('page-title', 'Purchase Orders')

@section('content')
    <x-ui.page-header title="Purchase Orders">
        @can('create supplier orders')
            <x-slot:actions>
                <x-ui.button :href="route('supplier-orders.create')">New PO</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="supplier-orders-search" placeholder="Search POs..." class="w-auto! min-w-48" />
                <x-ui.select id="supplier-orders-status-filter" class="w-auto! min-w-40">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="partially_received">Partially Received</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </x-ui.select>
                <x-ui.select id="supplier-orders-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="supplier-orders-table-body" pagination-id="supplier-orders-pagination">
            <x-slot:head>
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Linked Order</th>
                    <th>Items</th>
                    <th>Qty Ordered</th>
                    <th>Qty Received</th>
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
    $tableConfig = ['dataUrl' => route('supplier-orders.data')];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($tableConfig);

    const statusBadge = (status, label) => {
        const classes = {
            draft: 'bg-gray-100 text-gray-700',
            sent: 'bg-blue-100 text-blue-800',
            partially_received: 'bg-amber-100 text-amber-800',
            received: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800',
        };
        return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes[status] ?? 'bg-gray-100 text-gray-700'}">${escapeHtml(label)}</span>`;
    };

    const table = initAsyncTable({
        tbodyId: 'supplier-orders-table-body',
        paginationId: 'supplier-orders-pagination',
        dataUrl: config.dataUrl,
        columnCount: 9,
        emptyMessage: 'No purchase orders found.',
        getParams: () => ({
            search: document.getElementById('supplier-orders-search')?.value ?? '',
            status: document.getElementById('supplier-orders-status-filter')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('supplier-orders-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((order) => `
            <tr>
                <td>${escapeHtml(order.po_number)}</td>
                <td>${escapeHtml(order.supplier_name)}</td>
                <td>${escapeHtml(order.linked_order_number ?? '—')}</td>
                <td>${escapeHtml(order.item_count)}</td>
                <td>${escapeHtml(order.total_qty_ordered)}</td>
                <td>${escapeHtml(order.total_qty_received)}</td>
                <td>${statusBadge(order.status, order.status_label)}</td>
                <td>${escapeHtml(order.created_at)}</td>
                <td><a href="${escapeHtml(order.show_url)}" class="ui-row-action">View</a></td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('supplier-orders-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('supplier-orders-status-filter')?.addEventListener('change', () => table.loadData(1));
    document.getElementById('supplier-orders-per-page')?.addEventListener('change', () => table.loadData(1));
});
</script>
@endpush
