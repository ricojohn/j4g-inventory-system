@extends('layouts.app')

@section('page-title', 'Today')

@section('content')
    @php
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $firstName = collect(explode(' ', trim($greetingName)))->first() ?: $greetingName;
        $ordersHref = auth()->user()?->can('view orders') ? route('orders.index') : null;
        $inventoryHref = auth()->user()?->can('view products') ? route('products.index') : null;
        $financeHref = auth()->user()?->can('view finance') && \Illuminate\Support\Facades\Route::has('finance.index')
            ? route('finance.index')
            : null;
        $dueTotal = $dueTodayCount + $overdueCount;
    @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ strtoupper(now()->format('l, j F')) }}</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-gray-900">{{ $greeting }}, {{ $firstName }}.</h1>
            <p class="mt-0.5 text-sm text-gray-500">Here's what needs your attention across J4G today.</p>
        </div>
        @if ($ordersHref)
            <x-ui.button variant="secondary" :href="$ordersHref">View all orders</x-ui.button>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card
            label="Due today & overdue"
            icon="orders"
            :value="$dueTotal"
            :description="($overdueCount.' overdue · '.$dueTodayCount.' due today')"
            accent="danger"
            :href="$ordersHref"
        />
        <x-ui.stat-card
            label="Stock shortages"
            icon="low-stock"
            :value="$shortagePieces"
            :description="'pieces across '.$shortageSkuCount.' SKUs'"
            accent="warning"
            :href="$inventoryHref"
        />
        <x-ui.stat-card
            label="Receivables"
            icon="pos"
            :value="$receivablesDisplay"
            :description="$receivablesInvoiceCount.' invoices need action'"
            accent="info"
            :href="$financeHref"
        />
        <x-ui.stat-card
            label="Production blockers"
            icon="out-of-stock"
            :value="$productionBlockers"
            description="shortage · draft PO · follow-up"
            accent="info"
            :href="$ordersHref"
        />
    </div>

    @if ($primaryAction)
        <div class="mt-4 flex flex-col gap-3 rounded-xl bg-sidebar px-4 py-3 text-white sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[13px] font-medium">{{ $primaryAction['title'] }}</p>
                <p class="mt-0.5 text-[12px] text-white/65">{{ $primaryAction['subtitle'] }}</p>
            </div>
            <a href="{{ $primaryAction['href'] }}" class="shrink-0 text-[13px] font-medium text-white hover:underline">Review now →</a>
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-5">
        <x-ui.page-card class="lg:col-span-3">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Needs attention</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Highest-impact work, ordered by urgency.</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($attentionItems as $item)
                    <li>
                        <a href="{{ $item['href'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-gray-900">{{ $item['title'] }}</p>
                                <p class="mt-0.5 text-[12px] text-gray-500">{{ $item['subtitle'] }}</p>
                            </div>
                            <x-ui.status-pill :status="$item['tag']">{{ $item['tag'] }}</x-ui.status-pill>
                            <span class="text-gray-400" aria-hidden="true">→</span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-[13px] text-gray-500">Nothing urgent right now.</li>
                @endforelse
            </ul>
        </x-ui.page-card>

        <x-ui.page-card class="lg:col-span-2">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Reservation pulse</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">{{ $openOrders }} active orders</p>
            </div>
            <div class="space-y-3 p-4">
                @php $maxPulse = max(1, collect($pulse)->max('count')); @endphp
                @foreach ($pulse as $row)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-[12px]">
                            <span class="font-medium text-gray-700">{{ $row['label'] }}</span>
                            <span class="text-gray-500">{{ $row['count'] }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $row['color'] }}" style="width: {{ ($row['count'] / $maxPulse) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($ordersHref)
                <div class="border-t border-gray-200 px-4 py-3">
                    <a href="{{ route('orders.board') }}" class="text-[13px] font-medium text-brand hover:underline">Open orders board →</a>
                </div>
            @endif
        </x-ui.page-card>
    </div>

    <x-ui.page-card class="mt-4">
        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-4 text-[13px]">
                <span><span class="font-semibold text-gray-900">{{ number_format($totalAvailable) }}</span> <span class="text-gray-500">Available</span></span>
                <span><span class="font-semibold text-amber-700">{{ number_format($lowStockCount) }}</span> <span class="text-gray-500">Low stock</span></span>
                <span><span class="font-semibold text-red-700">{{ number_format($outOfStockCount) }}</span> <span class="text-gray-500">Out of stock</span></span>
                <span><span class="font-semibold text-gray-900">{{ number_format($openPos) }}</span> <span class="text-gray-500">Open POs</span></span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500">{{ number_format($totalStock) }} on hand · {{ number_format($totalReserved) }} reserved</span>
            </div>
            @if ($inventoryHref)
                <x-ui.button variant="secondary" :href="$inventoryHref">Review inventory</x-ui.button>
            @endif
        </div>
    </x-ui.page-card>
@endsection
