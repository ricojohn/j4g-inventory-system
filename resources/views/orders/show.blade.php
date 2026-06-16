@extends('layouts.app')

@section('page-title', $order->order_number)

@section('content')
    @php
        $statusClasses = [
            'pending' => 'bg-gray-100 text-gray-700',
            'reserved' => 'bg-blue-100 text-blue-800',
            'partially_reserved' => 'bg-amber-100 text-amber-800',
            'fulfilled' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ];
        $itemStatusClasses = $statusClasses;
        $hasShortage = $order->items->contains(
            fn ($item) => $item->quantity_ordered > $item->quantity_reserved
        );
        $showDraftPoBanner = $order->supplierOrder && $order->supplierOrder->status->value === 'draft';
        $showShortageBanner = $hasShortage
            && ! $order->supplier_order_id
            && ! in_array($order->status->value, ['fulfilled', 'cancelled'], true);
    @endphp

    <x-ui.page-header :title="$order->order_number">
        <x-slot:actions>
            @if ($order->status->value === 'reserved')
                @can('fulfill orders')
                    <x-ui.button type="button" id="fulfill-order-btn">Fulfill Order</x-ui.button>
                @endcan
            @endif
            @if (! in_array($order->status->value, ['fulfilled', 'cancelled'], true))
                @can('cancel orders')
                    <x-ui.button type="button" variant="danger" id="cancel-order-btn">Cancel Order</x-ui.button>
                @endcan
            @endif
            <x-ui.button variant="secondary" :href="route('orders.index')">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4">
        <span id="order-status-badge" class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusClasses[$order->status->value] ?? 'bg-gray-100 text-gray-700' }}">
            {{ $order->status->label() }}
        </span>
    </div>

    @if (session('shortage_notice'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            ℹ️ {{ session('shortage_notice') }}
        </div>
    @endif

    @if ($showDraftPoBanner)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            ⚠️ Stock was short for this order. A draft Purchase Order
            <strong>[{{ $order->supplierOrder->po_number }}]</strong> was created.
            Review and confirm it before sending to your supplier.
            <a href="{{ route('supplier-orders.show', $order->supplierOrder) }}" class="ml-1 font-medium underline">View Draft PO →</a>
        </div>
    @elseif ($showShortageBanner)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            ⚠️ Stock is short for this order. Create a purchase order to cover the remaining quantities.
            @can('create supplier orders')
                <a href="{{ route('supplier-orders.create', ['from_order_id' => $order->id]) }}" class="ml-1 font-medium underline">Create Purchase Order →</a>
            @endcan
        </div>
    @endif

    <x-ui.page-card class="mb-4">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Customer Information</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium text-gray-500">Name</p>
                <p class="mt-1 text-[13px] font-medium text-gray-900">{{ $order->customer_name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Contact</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $order->customer_contact ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Source</p>
                <p class="mt-1">
                    @if ($order->customer_source)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $order->customer_source->badgeColor() }}">
                            {{ $order->customer_source->icon() }} {{ $order->customer_source->label() }}
                        </span>
                    @else
                        <span class="text-[13px] text-gray-900">—</span>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Created By</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $order->creator?->name ?? 'System' }}</p>
            </div>
            @if ($order->customer_notes)
                <div class="sm:col-span-2 lg:col-span-4">
                    <p class="text-xs font-medium text-gray-500">Notes</p>
                    <p class="mt-1 text-[13px] text-gray-900">{{ $order->customer_notes }}</p>
                </div>
            @endif
        </div>
    </x-ui.page-card>

    @if ($order->imageUrl())
        <x-ui.page-card class="mb-4">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Order Reference Image</h2>
            </div>
            <div class="p-4">
                <a href="{{ $order->imageUrl() }}" target="_blank" rel="noopener noreferrer">
                    <img
                        src="{{ $order->imageUrl() }}"
                        alt="Order reference image for {{ $order->order_number }}"
                        class="max-h-64 rounded-lg border border-gray-200 object-contain hover:opacity-90"
                    />
                </a>
            </div>
        </x-ui.page-card>
    @endif

    <x-ui.page-card>
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Order Items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Product</th>
                        <th>Color</th>
                        <th>Size</th>
                        <th>Qty Ordered</th>
                        <th>Qty Reserved</th>
                        <th>Shortage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order->items as $item)
                        @php
                            $shortage = max(0, $item->quantity_ordered - $item->quantity_reserved);
                            $itemLabel = match ($item->status) {
                                'reserved' => 'Reserved',
                                'partially_reserved' => 'Partially Reserved',
                                'fulfilled' => 'Fulfilled',
                                'cancelled' => 'Cancelled',
                                default => 'Pending',
                            };
                        @endphp
                        <tr>
                            <td>{{ $item->cell->color->item_code }}</td>
                            <td>{{ $item->cell->color->product->name }}</td>
                            <td>
                                <x-ui.color-image-trigger
                                    :image-url="$item->cell->color->imageUrl()"
                                    :color-name="$item->cell->color->color->name"
                                    :item-code="$item->cell->color->item_code"
                                />
                            </td>
                            <td>{{ $item->cell->size->size->name }}</td>
                            <td>{{ $item->quantity_ordered }}</td>
                            <td>{{ $item->quantity_reserved }}</td>
                            <td>{{ $shortage }}</td>
                            <td>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $itemStatusClasses[$item->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $itemLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-[13px] text-gray-500">No line items.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.page-card>
@endsection

@push('scripts')
@php
    $actionConfig = [
        'fulfillUrl' => route('orders.fulfill', $order),
        'cancelUrl' => route('orders.cancel', $order),
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($actionConfig);

    const runAction = async (url, confirmMessage) => {
        if (confirmMessage && !confirm(confirmMessage)) return;

        try {
            await postData(url);
            showToast('Order updated.');
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Unable to update order.', 'error');
        }
    };

    document.getElementById('fulfill-order-btn')?.addEventListener('click', () => runAction(config.fulfillUrl, 'Fulfill this order and deduct stock?'));
    document.getElementById('cancel-order-btn')?.addEventListener('click', () => runAction(config.cancelUrl, 'Cancel this order and release reserved stock?'));
});
</script>
@endpush
