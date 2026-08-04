@extends('layouts.app')

@section('page-title', $customer->name)

@section('content')
    <x-ui.page-header
        eyebrow="Customer and team records"
        :title="$customer->name"
        subtitle="Profile details and recent orders."
    >
        <x-slot:actions>
            @if ($canManage)
                <x-ui.button :href="route('customers.edit', $customer)">Edit</x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('customers.index')">Back</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card class="mb-4">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Customer Details</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-medium text-gray-500">Handle</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $customer->handle ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Contact</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $customer->contact ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Source</p>
                <p class="mt-1 text-[13px] text-gray-900">
                    @if ($customer->source)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $customer->source->badgeColor() }}">
                            {{ $customer->source->label() }}
                        </span>
                    @else
                        —
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500">Orders</p>
                <p class="mt-1 text-[13px] text-gray-900">{{ $customer->orders->count() }}</p>
            </div>
            @if ($customer->notes)
                <div class="sm:col-span-2 lg:col-span-4">
                    <p class="text-xs font-medium text-gray-500">Notes</p>
                    <p class="mt-1 text-[13px] text-gray-900">{{ $customer->notes }}</p>
                </div>
            @endif
        </div>
    </x-ui.page-card>

    <x-ui.page-card>
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Recent orders</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customer->orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('orders.show', $order) }}" class="font-medium text-brand hover:underline">{{ $order->order_number }}</a>
                            </td>
                            <td>
                                <x-ui.status-pill :status="$order->status->value">{{ $order->status->label() }}</x-ui.status-pill>
                            </td>
                            <td>₱{{ number_format((float) $order->order_total, 2) }}</td>
                            <td>₱{{ number_format((float) $order->amount_paid, 2) }}</td>
                            <td>{{ $order->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-[13px] text-gray-500">No orders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.page-card>
@endsection
