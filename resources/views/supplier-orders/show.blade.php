@extends('layouts.app')

@section('page-title', $po->po_number)

@section('content')
    @php
        $statusClasses = [
            'draft' => 'bg-gray-100 text-gray-700',
            'sent' => 'bg-blue-100 text-blue-800',
            'partially_received' => 'bg-amber-100 text-amber-800',
            'received' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
        ];
    @endphp

    <x-ui.page-header :title="$po->po_number">
        <x-slot:actions>
            @if (! in_array($po->status->value, ['received', 'cancelled'], true))
                @can('receive supplier orders')
                    <x-ui.button type="button" id="open-receive-modal">Receive Delivery</x-ui.button>
                @endcan
            @endif
            @if (in_array($po->status->value, ['draft', 'sent'], true))
                @can('cancel supplier orders')
                    <x-ui.button type="button" variant="danger" id="cancel-po-btn">Cancel PO</x-ui.button>
                @endcan
            @endif
            <x-ui.button variant="secondary" :href="route('supplier-orders.index')">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4">
        <span id="po-status-badge" class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusClasses[$po->status->value] ?? 'bg-gray-100 text-gray-700' }}">
            {{ $po->status->label() }}
        </span>
    </div>

    @if ($po->customerOrder)
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-[13px] text-blue-900">
            ℹ️ This PO was auto-created for shortage on <strong>{{ $po->customerOrder->order_number }}</strong>.
            <a href="{{ route('orders.show', $po->customerOrder) }}" class="ml-1 font-medium underline">View Order →</a>
        </div>
    @endif

    <x-ui.page-card class="mb-4">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Supplier Information</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div>
                <p class="text-xs font-medium text-gray-500">Supplier</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $po->supplier?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Contact</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $po->supplier?->contact ?? '—' }}</p>
            </div>
            @if ($po->remarks)
                <div class="sm:col-span-2">
                    <p class="text-xs font-medium text-gray-500">Remarks</p>
                    <p class="mt-1 text-[13px] text-gray-900">{{ $po->remarks }}</p>
                </div>
            @endif
        </div>
    </x-ui.page-card>

    <x-ui.page-card>
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">PO Items</h2>
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
                        <th>Qty Received</th>
                        <th>Remaining</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($po->items as $item)
                        @php
                            $remaining = max(0, $item->quantity_ordered - $item->quantity_received);
                            $itemStatus = $item->quantity_received >= $item->quantity_ordered
                                ? 'Received'
                                : ($item->quantity_received > 0 ? 'Partial' : 'Pending');
                            $itemStatusClass = match (true) {
                                $item->quantity_received >= $item->quantity_ordered => 'bg-green-100 text-green-800',
                                $item->quantity_received > 0 => 'bg-amber-100 text-amber-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr>
                            <td>{{ $item->cell->color->item_code }}</td>
                            <td>{{ $item->cell->color->product->name }}</td>
                            <td>{{ $item->cell->color->color->name }}</td>
                            <td>{{ $item->cell->size->size->name }}</td>
                            <td>{{ $item->quantity_ordered }}</td>
                            <td>{{ $item->quantity_received }}</td>
                            <td>{{ $remaining }}</td>
                            <td>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $itemStatusClass }}">
                                    {{ $itemStatus }}
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

    <div id="receive-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
            <div class="ui-modal-header shrink-0">
                <h2 class="text-[13px] font-semibold text-gray-900">Receive Delivery — {{ $po->po_number }}</h2>
                <p class="mt-0.5 text-[13px] text-gray-500">Enter quantities to receive for each item.</p>
            </div>
            <form id="receive-form" class="flex min-h-0 flex-1 flex-col">
                <div class="flex min-h-0 flex-1 flex-col gap-3 p-4">
                    <div id="receive-rows-scroll" class="flex min-h-0 flex-1 overflow-y-auto rounded border border-gray-200">
                        <table class="ui-table w-full">
                            <thead class="sticky top-0 z-10 bg-gray-50">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Qty Ordered</th>
                                    <th>Qty Received</th>
                                    <th>Receive Now</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($po->items as $item)
                                    @php $remaining = max(0, $item->quantity_ordered - $item->quantity_received); @endphp
                                    @if ($remaining > 0)
                                        <tr>
                                            <td>{{ $item->cell->color->item_code }}</td>
                                            <td>{{ $item->quantity_ordered }}</td>
                                            <td>{{ $item->quantity_received }}</td>
                                            <td>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $remaining }}"
                                                    value="{{ $remaining }}"
                                                    class="receive-qty h-9 w-24 rounded-lg border border-gray-300 px-3 text-[13px] text-gray-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                                    data-item-id="{{ $item->id }}"
                                                    data-max="{{ $remaining }}"
                                                />
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ui-modal-footer shrink-0">
                    <x-ui.button type="button" variant="secondary" data-close="receive-modal">Cancel</x-ui.button>
                    <x-ui.button type="button" id="submit-receive">Confirm Receipt</x-ui.button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
@php
    $actionConfig = [
        'receiveUrl' => route('supplier-orders.receive', $po),
        'cancelUrl' => route('supplier-orders.cancel', $po),
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($actionConfig);
    const modal = document.getElementById('receive-modal');

    const closeOverlay = (overlay) => overlay.classList.add('hidden');

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeOverlay(modal);
        });
        modal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', () => closeOverlay(modal));
        });
    }

    document.getElementById('open-receive-modal')?.addEventListener('click', () => {
        document.getElementById('receive-rows-scroll')?.scrollTo(0, 0);
        modal?.classList.remove('hidden');
    });

    document.getElementById('submit-receive')?.addEventListener('click', async () => {
        const qtys = {};
        let hasQty = false;
        let exceedsMax = false;

        modal?.querySelectorAll('.receive-qty').forEach((input) => {
            const qty = Number(input.value ?? 0);
            const max = Number(input.dataset.max ?? 0);

            if (qty <= 0) return;

            if (qty > max) {
                exceedsMax = true;
                return;
            }

            qtys[input.dataset.itemId] = qty;
            hasQty = true;
        });

        if (exceedsMax) {
            showToast('Receive quantity exceeds remaining for one or more items.', 'error');
            return;
        }

        if (!hasQty) {
            showToast('Enter a quantity to receive.', 'error');
            return;
        }

        try {
            const response = await postData(config.receiveUrl, { qtys });
            closeOverlay(modal);
            showToast(response.message || 'Delivery received.');
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Unable to receive delivery.', 'error');
        }
    });

    document.getElementById('cancel-po-btn')?.addEventListener('click', async () => {
        if (!confirm('Cancel this purchase order?')) return;

        try {
            await postData(config.cancelUrl);
            showToast('Purchase order cancelled.');
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Unable to cancel PO.', 'error');
        }
    });
});
</script>
@endpush
