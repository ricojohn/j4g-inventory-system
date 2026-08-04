@extends('layouts.app')

@section('page-title', $order->order_number)

@section('content')
    @php
        $hasShortage = $shortageQty > 0;
        $showDraftPoBanner = $order->supplierOrder && $order->supplierOrder->status->value === 'draft';
        $showShortageBanner = $hasShortage
            && ! $order->supplier_order_id
            && ! in_array($order->status->value, ['fulfilled', 'cancelled'], true);
        $tabs = [
            'overview' => 'Overview',
            'items' => 'Items & stock',
            'layouts' => 'Layouts',
            'invoice' => 'Invoice & payments',
            'production' => 'Production',
            'delivery' => 'Delivery',
            'history' => 'History',
        ];
        $ordered = (int) $order->items->sum('quantity_ordered');
        $reserved = (int) $order->items->sum('quantity_reserved');
    @endphp

    <div class="mb-3">
        <a href="{{ route('orders.index') }}" class="text-[13px] font-medium text-gray-500 hover:text-gray-800">← Back to orders</a>
    </div>

    @php
        $headerLayout = $order->approvedLayout() ?? $order->latestLayout();
        $headerLayoutUrl = $headerLayout?->fileUrl() ?? $order->imageUrl();
    @endphp

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 flex-1 items-start gap-4">
                @if ($headerLayoutUrl)
                    <a
                        href="{{ $headerLayoutUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group relative shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                        title="{{ $headerLayout ? 'v'.$headerLayout->version.' — '.$headerLayout->title : 'Layout image' }}"
                    >
                        <img
                            src="{{ $headerLayoutUrl }}"
                            alt="Order layout"
                            class="h-20 w-20 object-cover transition group-hover:opacity-90 sm:h-24 sm:w-24"
                        />
                    </a>
                @endif
                <div class="min-w-0">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Customer order</p>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">{{ $order->order_number }}</h1>
                    <p class="mt-0.5 text-sm text-gray-500">
                        @if ($order->customer)
                            <a href="{{ route('customers.show', $order->customer) }}" class="text-brand hover:underline">{{ $order->customer_name }}</a>
                        @else
                            {{ $order->customer_name }}
                        @endif
                        @if ($order->customer_contact)
                            · {{ $order->customer_contact }}
                        @endif
                    </p>
                    @if ($headerLayout)
                        <p class="mt-1 text-[12px] text-gray-500">
                            Layout v{{ $headerLayout->version }} · {{ $headerLayout->status->label() }}
                            <a href="{{ route('orders.show', ['order' => $order, 'tab' => 'layouts']) }}" class="ml-1 font-medium text-brand hover:underline">Manage</a>
                        </p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status-pill :status="$order->status->value">{{ $order->status->label() }}</x-ui.status-pill>
                <x-ui.status-pill :status="$paymentStatus->value">{{ $paymentStatus->label() }}</x-ui.status-pill>
                @if ($order->status->value === 'reserved')
                    @can('fulfill orders')
                        <x-ui.button type="button" id="fulfill-order-btn">Fulfill</x-ui.button>
                    @endcan
                @endif
                @if (! in_array($order->status->value, ['fulfilled', 'cancelled'], true))
                    @can('cancel orders')
                        <x-ui.button type="button" variant="danger" id="cancel-order-btn">Cancel</x-ui.button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-4">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Due date</p>
                <p class="mt-1 text-[13px] font-medium text-gray-900">{{ $order->due_date?->format('M j, Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Order total</p>
                <p class="mt-1 text-[13px] font-medium text-gray-900">₱{{ number_format((float) $order->order_total, 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Balance</p>
                <p class="mt-1 text-[13px] font-medium text-gray-900">₱{{ number_format($balanceDue, 2) }}</p>
            </div>
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Assigned</p>
                <p class="mt-1 text-[13px] font-medium text-gray-900">{{ $order->creator?->name ?? 'System' }}</p>
            </div>
        </div>
    </div>

    @if (session('shortage_notice'))
        <div class="ui-alert-warning mb-4">{{ session('shortage_notice') }}</div>
    @endif

    @if ($showDraftPoBanner)
        <div class="ui-alert-warning mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $shortageQty }} pieces are still short. Draft PO <strong>{{ $order->supplierOrder->po_number }}</strong> is linked.</span>
            <a href="{{ route('supplier-orders.show', $order->supplierOrder) }}" class="shrink-0 font-medium text-amber-900 underline">Review shortage</a>
        </div>
    @elseif ($showShortageBanner)
        <div class="ui-alert-warning mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $shortageQty }} pieces are still short. Create a purchase order to cover remaining quantities.</span>
            @can('create supplier orders')
                <a href="{{ route('supplier-orders.create', ['from_order_id' => $order->id]) }}" class="shrink-0 font-medium text-amber-900 underline">Review shortage</a>
            @endcan
        </div>
    @elseif ($hasShortage && $order->supplierOrder)
        <div class="ui-alert-warning mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $shortageQty }} pieces are still short. Procurement request {{ $order->supplierOrder->po_number }} is linked.</span>
            <a href="{{ route('supplier-orders.show', $order->supplierOrder) }}" class="shrink-0 font-medium text-amber-900 underline">Review shortage</a>
        </div>
    @endif

    <div class="mb-4 flex gap-4 overflow-x-auto border-b border-gray-200">
        @foreach ($tabs as $key => $label)
            <a
                href="{{ route('orders.show', ['order' => $order, 'tab' => $key]) }}"
                @class(['ui-tab whitespace-nowrap', 'ui-tab-active' => $activeTab === $key])
            >
                {{ $label }}
                @if ($key === 'items' && $shortageQty > 0)
                    <span class="rounded-full bg-red-600 px-1.5 text-[10px] font-semibold text-white">{{ $shortageQty }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if ($activeTab === 'overview')
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-3">
                <x-ui.page-card>
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-[13px] font-semibold text-gray-900">Next best action</h2>
                        <p class="mt-0.5 text-[12px] text-gray-500">Move this order forward safely.</p>
                    </div>
                    <div class="p-4">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ $nextAction['tag'] }} · {{ strtoupper($nextAction['priority']) }} PRIORITY</p>
                        <p class="mt-2 text-[15px] font-semibold text-gray-900">{{ $nextAction['label'] }}</p>
                        @if ($nextAction['href'])
                            <div class="mt-4">
                                <x-ui.button :href="$nextAction['href']">Open task →</x-ui.button>
                            </div>
                        @endif
                    </div>
                </x-ui.page-card>

                <x-ui.page-card>
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-[13px] font-semibold text-gray-900">Order readiness</h2>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($readiness as $item)
                            <li class="flex items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <p class="text-[13px] font-medium text-gray-900">{{ $item['label'] }}</p>
                                    @if ($item['detail'])
                                        <p class="mt-0.5 text-[12px] text-gray-500">{{ $item['detail'] }}</p>
                                    @endif
                                </div>
                                @if ($item['ready'])
                                    <x-ui.status-pill status="ready">Ready</x-ui.status-pill>
                                @else
                                    <x-ui.status-pill status="partial">Needs action</x-ui.status-pill>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.page-card>
            </div>

            <div class="lg:col-span-2 space-y-4">
                <x-ui.page-card>
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h2 class="text-[13px] font-semibold text-gray-900">Customer</h2>
                    </div>
                    <div class="space-y-3 p-4 text-[13px]">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Name</p>
                            <p class="mt-1 font-medium text-gray-900">{{ $order->customer_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Contact</p>
                            <p class="mt-1 text-gray-900">{{ $order->customer_contact ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Source</p>
                            <p class="mt-1">
                                @if ($order->customer_source)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $order->customer_source->badgeColor() }}">
                                        {{ $order->customer_source->icon() }} {{ $order->customer_source->label() }}
                                    </span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        @if ($order->customer_notes)
                            <div>
                                <p class="text-xs font-medium text-gray-500">Notes</p>
                                <p class="mt-1 text-gray-900">{{ $order->customer_notes }}</p>
                            </div>
                        @endif
                    </div>
                </x-ui.page-card>

                @php
                    $layout = $order->approvedLayout() ?? $order->latestLayout();
                    $layoutUrl = $layout?->fileUrl() ?? $order->imageUrl();
                @endphp
                @if ($layoutUrl)
                    <x-ui.page-card>
                        <div class="border-b border-gray-200 px-4 py-3">
                            <h2 class="text-[13px] font-semibold text-gray-900">Layout Image</h2>
                            @if ($layout)
                                <p class="mt-0.5 text-[12px] text-gray-500">
                                    v{{ $layout->version }} — {{ $layout->title }} · {{ $layout->status->label() }}
                                </p>
                            @endif
                        </div>
                        <div class="p-4">
                            <a href="{{ $layoutUrl }}" target="_blank" rel="noopener noreferrer" class="block">
                                <img
                                    src="{{ $layoutUrl }}"
                                    alt="Order layout"
                                    class="max-h-64 w-full rounded-lg border border-gray-200 object-contain"
                                />
                            </a>
                            <div class="mt-3">
                                <a href="{{ route('orders.show', ['order' => $order, 'tab' => 'layouts']) }}" class="text-[12px] font-medium text-brand hover:underline">
                                    Manage layouts →
                                </a>
                            </div>
                        </div>
                    </x-ui.page-card>
                @elseif ($layout)
                    <x-ui.page-card>
                        <div class="border-b border-gray-200 px-4 py-3">
                            <h2 class="text-[13px] font-semibold text-gray-900">Layout Image</h2>
                            <p class="mt-0.5 text-[12px] text-gray-500">
                                v{{ $layout->version }} — {{ $layout->title }} · {{ $layout->status->label() }}
                            </p>
                        </div>
                        <div class="p-4">
                            <p class="text-[13px] text-amber-800">Layout file is missing from storage. Re-upload it from the Layouts tab.</p>
                            <div class="mt-3">
                                <a href="{{ route('orders.show', ['order' => $order, 'tab' => 'layouts']) }}" class="text-[12px] font-medium text-brand hover:underline">
                                    Manage layouts →
                                </a>
                            </div>
                        </div>
                    </x-ui.page-card>
                @endif
            </div>
        </div>
    @elseif ($activeTab === 'items')
        <x-ui.page-card>
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <div>
                    <h2 class="text-[13px] font-semibold text-gray-900">Order items & reservation</h2>
                    <p class="mt-0.5 text-[12px] text-gray-500">{{ $reserved }}/{{ $ordered }} reserved</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="ui-table w-full">
                    <thead>
                        <tr>
                            <th>Variant</th>
                            <th>Requested</th>
                            <th>Reserved</th>
                            <th>Unit price</th>
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
                                    'partially_reserved' => 'Partial',
                                    'fulfilled' => 'Fulfilled',
                                    'cancelled' => 'Cancelled',
                                    default => 'Pending',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-medium text-gray-900">{{ $item->cell->size->size->name }} {{ $item->cell->color->product->name }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-500">{{ $item->cell->color->item_code }} · {{ $item->cell->color->color->name }}</p>
                                </td>
                                <td>{{ $item->quantity_ordered }}</td>
                                <td>{{ $item->quantity_reserved }}</td>
                                <td>₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>{{ $shortage }}</td>
                                <td>
                                    <x-ui.status-pill :status="$item->status === 'partially_reserved' ? 'partial' : $item->status">{{ $itemLabel }}</x-ui.status-pill>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-[13px] text-gray-500">No line items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($order->supplierOrder)
                <div class="border-t border-gray-200 px-4 py-3 text-[13px]">
                    Linked PO:
                    <a href="{{ route('supplier-orders.show', $order->supplierOrder) }}" class="font-medium text-brand hover:underline">{{ $order->supplierOrder->po_number }}</a>
                    ({{ $order->supplierOrder->status->label() }})
                </div>
            @endif
        </x-ui.page-card>
    @elseif (view()->exists('orders.partials.tab-'.$activeTab))
        @include('orders.partials.tab-'.$activeTab)
    @else
        <x-ui.page-card>
            <div class="px-4 py-10 text-center">
                <p class="text-[13px] font-medium text-gray-900">{{ $tabs[$activeTab] ?? 'Section' }} coming soon</p>
            </div>
        </x-ui.page-card>
    @endif
@endsection

@push('scripts')
@php
    $actionConfig = [
        'fulfillUrl' => route('orders.fulfill', $order),
        'cancelUrl' => route('orders.cancel', $order),
        'advanceUrl' => route('production.advance', $order),
        'canAdvanceProduction' => $canManageProduction
            && ! $order->production_blocked
            && ($order->production_stage ?? \App\Enums\ProductionStage::Ready)->next() !== null,
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

    if (config.canAdvanceProduction) {
        document.getElementById('advance-production-btn')?.addEventListener('click', () =>
            runAction(config.advanceUrl, 'Advance this order to the next production stage?')
        );
    }
});
</script>
@endpush
