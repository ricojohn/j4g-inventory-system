@extends('layouts.app')

@section('page-title', 'Finance')

@section('content')
    <x-ui.page-header
        eyebrow="Operational receivables"
        title="Finance"
        subtitle="Open balances, deposits awaiting payment, and weekly clearances."
    />

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat-card
            label="Open receivables"
            :value="'₱'.number_format((float) $openReceivables, 2)"
            description="Balance still due"
            accent="warning"
            icon="pos"
        />
        <x-ui.stat-card
            label="Awaiting DP"
            :value="$awaitingDpCount"
            description="Orders with total but no payment"
            accent="info"
            icon="orders"
        />
        <x-ui.stat-card
            label="Cleared this week"
            :value="'₱'.number_format((float) $clearedThisWeek, 2)"
            description="Payments posted this week"
            icon="available"
        />
    </div>

    <x-ui.page-card>
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Balances needing action</h2>
            <p class="mt-0.5 text-[12px] text-gray-500">Orders with remaining balance due.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Due date</th>
                        <th>Balance</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($openOrders as $order)
                        @php
                            $balance = (float) $order->order_total - (float) $order->amount_paid;
                        @endphp
                        <tr>
                            <td>{{ $order->customer_name }}</td>
                            <td>
                                <a href="{{ route('orders.show', ['order' => $order, 'tab' => 'invoice']) }}" class="font-medium text-brand hover:underline">{{ $order->order_number }}</a>
                            </td>
                            <td>{{ $order->due_date?->format('M d, Y') ?? '—' }}</td>
                            <td class="font-medium text-amber-700">₱{{ number_format($balance, 2) }}</td>
                            <td>
                                <a href="{{ route('orders.show', ['order' => $order, 'tab' => 'invoice']) }}" class="ui-row-action">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[13px] text-gray-500">No balances needing action.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.page-card>
@endsection
