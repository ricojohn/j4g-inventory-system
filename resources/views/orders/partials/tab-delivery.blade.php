<x-ui.page-card>
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-[13px] font-semibold text-gray-900">Delivery</h2>
        <p class="mt-0.5 text-[12px] text-gray-500">Capture handoff details and release when ready.</p>
    </div>

    <div class="space-y-4 p-4">
        @if ($order->released_at)
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-[13px] text-green-900">
                Released {{ $order->released_at->format('M d, Y H:i') }}
                @if ($order->release_override_reason)
                    · Override: {{ $order->release_override_reason }}
                    @if ($order->releaseOverrideBy)
                        (by {{ $order->releaseOverrideBy->name }})
                    @endif
                @endif
            </div>
        @endif

        @if ($canFulfill && ! $order->released_at)
            <form method="POST" action="{{ route('orders.delivery.update', $order) }}" class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-3">
                @csrf
                @method('PUT')
                <div>
                    <x-ui.label for="delivery_method">Delivery method</x-ui.label>
                    <x-ui.input id="delivery_method" name="delivery_method" type="text" :value="old('delivery_method', $order->delivery_method)" placeholder="Pickup, Lalamove, courier…" />
                    @error('delivery_method')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.label for="receiver_name">Receiver</x-ui.label>
                    <x-ui.input id="receiver_name" name="receiver_name" type="text" :value="old('receiver_name', $order->receiver_name)" />
                    @error('receiver_name')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.label for="proof_or_tracking">Proof / tracking</x-ui.label>
                    <x-ui.input id="proof_or_tracking" name="proof_or_tracking" type="text" :value="old('proof_or_tracking', $order->proof_or_tracking)" />
                    @error('proof_or_tracking')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.button type="submit">Save delivery</x-ui.button>
                </div>
            </form>

            @if ($balanceDue > 0)
                <div class="ui-alert-warning rounded-md px-3 py-2 text-[13px]">
                    Release blocked — balance of ₱{{ number_format((float) $balanceDue, 2) }} remains. Provide an owner override reason to continue.
                </div>
            @endif

            <form method="POST" action="{{ route('orders.release', $order) }}" class="space-y-3 rounded-lg border border-gray-200 p-3">
                @csrf
                @if ($balanceDue > 0)
                    <div>
                        <x-ui.label for="release_override_reason">Override reason *</x-ui.label>
                        <x-ui.textarea id="release_override_reason" name="release_override_reason" rows="2" required>{{ old('release_override_reason') }}</x-ui.textarea>
                        @error('release_override_reason')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <x-ui.button type="submit" :variant="$balanceDue > 0 ? 'danger' : 'primary'">
                    {{ $balanceDue > 0 ? 'Release with override' : 'Release order' }}
                </x-ui.button>
            </form>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-medium text-gray-500">Method</p>
                    <p class="mt-1 text-[13px] text-gray-900">{{ $order->delivery_method ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Receiver</p>
                    <p class="mt-1 text-[13px] text-gray-900">{{ $order->receiver_name ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Proof / tracking</p>
                    <p class="mt-1 text-[13px] text-gray-900">{{ $order->proof_or_tracking ?: '—' }}</p>
                </div>
            </div>
        @endif
    </div>
</x-ui.page-card>
