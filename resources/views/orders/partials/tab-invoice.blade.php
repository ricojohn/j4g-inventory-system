@php
    $isPaid = $paymentStatus === \App\Enums\OrderPaymentStatus::Paid || $balanceDue <= 0;
    $invoiceNumber = 'INV-'.$order->created_at->format('Y').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
    $clearedPayments = (float) $order->amount_paid;
    $orderTotal = (float) $order->order_total;
    $activePayments = $order->payments->whereNull('reversed_at')->sortByDesc('posted_at');
    $showPaymentForm = $canManageFinance && ! $isPaid && ($errors->has('amount') || $errors->has('method') || $errors->has('reference') || $errors->has('notes') || old('amount'));
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-ui.page-card>
        <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-4 py-3">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ $invoiceNumber }}</p>
                <h2 class="mt-0.5 text-[13px] font-semibold text-gray-900">Invoice summary</h2>
            </div>
            <x-ui.status-pill :status="$paymentStatus->value">{{ $paymentStatus->label() }}</x-ui.status-pill>
        </div>

        <div class="space-y-4 p-4">
            <div class="rounded-xl bg-slate-50 px-4 py-5 text-center">
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Balance due</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-brand">₱{{ number_format($balanceDue, 2) }}</p>
                <p class="mt-1 text-[12px] text-gray-500">of ₱{{ number_format($orderTotal, 2) }}</p>
            </div>

            <dl class="divide-y divide-gray-100 text-[13px]">
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <dt class="text-gray-500">Order snapshot</dt>
                    <dd class="font-medium text-gray-900">₱{{ number_format($orderTotal, 2) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <dt class="text-gray-500">Cleared payments</dt>
                    <dd class="font-medium text-green-700">-₱{{ number_format($clearedPayments, 2) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <dt class="font-medium text-gray-900">Balance</dt>
                    <dd class="font-semibold text-gray-900">₱{{ number_format($balanceDue, 2) }}</dd>
                </div>
            </dl>

            <p class="text-[12px] text-gray-500">Due date: {{ $order->due_date?->format('M d, Y') ?? '—' }}</p>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-4">
                <x-ui.button type="button" variant="secondary" onclick="window.print()">Print invoice</x-ui.button>
                @if ($canManageFinance && ! $isPaid)
                    <x-ui.button type="button" id="toggle-record-payment-btn">+ Record payment</x-ui.button>
                @endif
            </div>

            @if ($canManageFinance && ! $isPaid)
                <div id="record-payment-panel" @class(['mt-2 rounded-lg border border-gray-200 p-3', 'hidden' => ! $showPaymentForm])>
                    <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div>
                            <x-ui.label for="payment_amount">Amount *</x-ui.label>
                            <x-ui.input
                                id="payment_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="{{ number_format($balanceDue, 2, '.', '') }}"
                                required
                                value="{{ old('amount', number_format($balanceDue, 2, '.', '')) }}"
                            />
                            <p class="mt-1 text-[11px] text-gray-500">Max ₱{{ number_format($balanceDue, 2) }}</p>
                            @error('amount')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <x-ui.label for="payment_method">Method *</x-ui.label>
                            <x-ui.select id="payment_method" name="method" required>
                                <option value="cash" @selected(old('method') === 'cash')>Cash</option>
                                <option value="gcash" @selected(old('method', 'gcash') === 'gcash')>GCash</option>
                                <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>Bank transfer</option>
                                <option value="card" @selected(old('method') === 'card')>Card</option>
                            </x-ui.select>
                            @error('method')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <x-ui.label for="payment_reference">Reference</x-ui.label>
                            <x-ui.input id="payment_reference" name="reference" type="text" value="{{ old('reference') }}" placeholder="e.g. GCash Ref" />
                        </div>
                        <div>
                            <x-ui.label for="payment_notes">Notes</x-ui.label>
                            <x-ui.input id="payment_notes" name="notes" type="text" value="{{ old('notes') }}" />
                        </div>
                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <x-ui.button type="submit">Save payment</x-ui.button>
                            <x-ui.button type="button" variant="secondary" id="cancel-record-payment-btn">Cancel</x-ui.button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </x-ui.page-card>

    <x-ui.page-card>
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Payment ledger</h2>
            <p class="mt-0.5 text-[12px] text-gray-500">Posted records are reversed, never edited.</p>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse ($order->payments->sortByDesc('posted_at') as $payment)
                <div @class(['flex items-start gap-3 px-4 py-3', 'opacity-60' => $payment->isReversed()])>
                    <div @class([
                        'mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[13px] font-semibold text-white',
                        'bg-gray-400' => $payment->isReversed(),
                        'bg-green-600' => ! $payment->isReversed(),
                    ])>
                        ₱
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[13px] font-semibold text-gray-900">₱{{ number_format((float) $payment->amount, 2) }}</p>
                            @if ($payment->isReversed())
                                <x-ui.status-pill status="cancelled" :dot="true">Reversed</x-ui.status-pill>
                            @else
                                <x-ui.status-pill status="cleared">Cleared</x-ui.status-pill>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[13px] text-gray-700">
                            {{ ucfirst(str_replace('_', ' ', $payment->method)) }}
                            @if ($payment->reference)
                                Ref {{ $payment->reference }}
                            @endif
                        </p>
                        <p class="mt-0.5 text-[12px] text-gray-500">
                            {{ $payment->posted_at?->format('d M Y') ?? '—' }}
                            @if ($payment->recorder)
                                · {{ $payment->recorder->name }}
                            @endif
                            @if ($payment->notes)
                                · {{ $payment->notes }}
                            @endif
                        </p>
                        @if ($payment->isReversed() && $payment->reversal_reason)
                            <p class="mt-1 text-[12px] text-red-600">{{ $payment->reversal_reason }}</p>
                        @endif
                        @if (! $payment->isReversed() && $canManageFinance)
                            <form
                                method="POST"
                                action="{{ route('orders.payments.reverse', [$order, $payment]) }}"
                                class="payment-reversal-form mt-2 hidden"
                                onsubmit="return confirm('Reverse this payment?')"
                            >
                                @csrf
                                <input type="hidden" name="reversal_reason" value="Manual reversal" />
                                <button type="submit" class="ui-row-action ui-row-action-danger">Reverse payment</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-[13px] text-gray-500">No payments recorded.</div>
            @endforelse
        </div>

        @if ($canManageFinance && $activePayments->isNotEmpty())
            <div class="border-t border-gray-100 px-4 py-3">
                <button type="button" id="toggle-reversal-controls" class="text-[12px] font-medium text-brand hover:underline">
                    View reversal controls
                </button>
            </div>
        @endif
    </x-ui.page-card>
</div>

@if ($canManageFinance)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('record-payment-panel');
    document.getElementById('toggle-record-payment-btn')?.addEventListener('click', () => {
        panel?.classList.remove('hidden');
        document.getElementById('payment_amount')?.focus();
    });
    document.getElementById('cancel-record-payment-btn')?.addEventListener('click', () => {
        panel?.classList.add('hidden');
    });
    document.getElementById('toggle-reversal-controls')?.addEventListener('click', (event) => {
        document.querySelectorAll('.payment-reversal-form').forEach((form) => form.classList.toggle('hidden'));
        event.currentTarget.textContent = event.currentTarget.textContent.includes('Hide')
            ? 'View reversal controls'
            : 'Hide reversal controls';
    });
});
</script>
@endif
