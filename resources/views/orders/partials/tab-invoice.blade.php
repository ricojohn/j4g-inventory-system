<x-ui.page-card>
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-[13px] font-semibold text-gray-900">Invoice & payments</h2>
        <p class="mt-0.5 text-[12px] text-gray-500">Balance due and payment ledger for this order.</p>
    </div>

    <div class="space-y-4 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs font-medium text-gray-500">Order total</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">₱{{ number_format((float) $order->order_total, 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs font-medium text-gray-500">Amount paid</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">₱{{ number_format((float) $order->amount_paid, 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs font-medium text-gray-500">Balance due</p>
                <p class="mt-1 text-lg font-semibold text-amber-700">₱{{ number_format((float) $balanceDue, 2) }}</p>
                <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $paymentStatus->badgeColor() }}">
                    {{ $paymentStatus->label() }}
                </span>
            </div>
        </div>

        <p class="text-[12px] text-gray-500">Due date: {{ $order->due_date?->format('M d, Y') ?? '—' }}</p>

        @if ($canManageFinance)
            <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-4">
                @csrf
                <div>
                    <x-ui.label for="payment_amount">Amount *</x-ui.label>
                    <x-ui.input id="payment_amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount') }}" />
                    @error('amount')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.label for="payment_method">Method *</x-ui.label>
                    <x-ui.select id="payment_method" name="method" required>
                        <option value="cash" @selected(old('method') === 'cash')>Cash</option>
                        <option value="gcash" @selected(old('method') === 'gcash')>GCash</option>
                        <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>Bank transfer</option>
                        <option value="card" @selected(old('method') === 'card')>Card</option>
                    </x-ui.select>
                    @error('method')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.label for="payment_reference">Reference</x-ui.label>
                    <x-ui.input id="payment_reference" name="reference" type="text" value="{{ old('reference') }}" />
                </div>
                <div class="flex items-end">
                    <x-ui.button type="submit" class="w-full">Record payment</x-ui.button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Posted</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order->payments->sortByDesc('posted_at') as $payment)
                        <tr @class(['opacity-50' => $payment->isReversed()])>
                            <td>{{ $payment->posted_at?->format('M d, Y H:i') ?? '—' }}</td>
                            <td>₱{{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->method }}</td>
                            <td>{{ $payment->reference ?: '—' }}</td>
                            <td>{{ $payment->recorder?->name ?? '—' }}</td>
                            <td>
                                @if ($payment->isReversed())
                                    <span class="text-[11px] text-red-600">Reversed</span>
                                @elseif ($canManageFinance)
                                    <form method="POST" action="{{ route('orders.payments.reverse', [$order, $payment]) }}" class="inline" onsubmit="return confirm('Reverse this payment?')">
                                        @csrf
                                        <input type="hidden" name="reversal_reason" value="Manual reversal" />
                                        <button type="submit" class="ui-row-action ui-row-action-danger">Reverse</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[13px] text-gray-500">No payments recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-ui.page-card>
