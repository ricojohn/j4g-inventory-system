@php
    $stages = \App\Enums\ProductionStage::boardColumns();
    $current = $order->production_stage ?? \App\Enums\ProductionStage::Ready;
    $currentIndex = collect($stages)->search(fn ($stage) => $stage === $current);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }
@endphp

<x-ui.page-card>
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-[13px] font-semibold text-gray-900">Production</h2>
        <p class="mt-0.5 text-[12px] text-gray-500">Stage progress from ready through completed.</p>
    </div>

    <div class="space-y-4 p-4">
        @if ($order->production_blocked)
            <div class="ui-alert-danger rounded-md px-3 py-2 text-[13px]">
                Production is blocked for this order.
            </div>
        @endif

        <div class="flex gap-1 overflow-x-auto pb-1">
            @foreach ($stages as $index => $stage)
                @php
                    $isCurrent = $stage === $current;
                    $isPast = $index < $currentIndex;
                @endphp
                <div
                    @class([
                        'min-w-[88px] flex-1 rounded-lg border px-2 py-2 text-center',
                        'border-brand bg-brand-soft' => $isCurrent,
                        'border-green-200 bg-green-50' => $isPast && ! $isCurrent,
                        'border-gray-200 bg-white' => ! $isPast && ! $isCurrent,
                    ])
                >
                    <p @class([
                        'text-[11px] font-medium',
                        'text-brand' => $isCurrent,
                        'text-green-700' => $isPast && ! $isCurrent,
                        'text-gray-500' => ! $isPast && ! $isCurrent,
                    ])>{{ $stage->label() }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium text-gray-500">Current stage</p>
                <p class="mt-1 text-[13px] font-medium text-gray-900">{{ $current->label() }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Blocked</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $order->production_blocked ? 'Yes' : 'No' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Reservation status</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $order->status->label() }}</p>
            </div>
        </div>

        @if ($canManageProduction && ! $order->production_blocked && $current->next())
            <div>
                <x-ui.button type="button" id="advance-production-btn">
                    Advance to {{ $current->next()->label() }}
                </x-ui.button>
            </div>
        @endif
    </div>
</x-ui.page-card>
