@extends('layouts.app')
@section('title', 'Messenger Conversation')
@section('content')
<div class="space-y-4">
    <x-ui.page-header title="Messenger conversation" subtitle="{{ $conversation->page->name }} · {{ $conversation->psid }}" />
    <div class="flex gap-2">
        @if ($conversation->control_mode === 'ai')
            @can('take over messenger conversations')<form method="POST" action="{{ route('messenger.take-over', $conversation) }}">@csrf <x-ui.button type="submit">Take Over</x-ui.button></form>@endcan
        @else
            @can('take over messenger conversations')<form method="POST" action="{{ route('messenger.return-to-ai', $conversation) }}">@csrf <x-ui.button type="submit">Return to AI</x-ui.button></form>@endcan
        @endif
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.page-card>
            <h2 class="mb-3 font-semibold">Messages</h2>
            <div class="max-h-[34rem] space-y-2 overflow-y-auto">
                @forelse ($conversation->messages as $message)
                    <div class="rounded-lg p-3 {{ $message->direction === 'inbound' ? 'bg-gray-100' : 'bg-blue-50' }}"><p class="text-xs text-gray-500">{{ ucfirst($message->sender_type) }} · {{ $message->created_at->format('M d, H:i') }}</p><p class="whitespace-pre-wrap text-sm">{{ $message->body ?: '['.$message->message_type.']' }}</p></div>
                @empty <p class="text-sm text-gray-500">No messages.</p> @endforelse
            </div>
            @if ($conversation->control_mode === 'human')
                @can('take over messenger conversations')<form class="mt-4 space-y-2" method="POST" action="{{ route('messenger.reply', $conversation) }}">@csrf <x-ui.textarea name="message" rows="3" placeholder="Reply as staff" required /><x-ui.button type="submit">Send Reply</x-ui.button></form>@endcan
            @endif
        </x-ui.page-card>
        <x-ui.page-card>
            <h2 class="mb-3 font-semibold">Order draft</h2>
            @if ($draft = $conversation->draft)
                <form method="POST" action="{{ route('messenger.draft.update', $conversation) }}" class="space-y-3">@csrf @method('PUT')
                    <x-ui.input name="customer_name" value="{{ $draft->customer_name }}" placeholder="Customer name" required />
                    <select name="fulfillment_method" class="w-full rounded-md border-gray-300 text-sm" required><option value="">Delivery or pickup</option><option value="delivery" @selected($draft->fulfillment_method === 'delivery')>Delivery</option><option value="pickup" @selected($draft->fulfillment_method === 'pickup')>Pickup</option></select>
                    <x-ui.textarea name="delivery_address" rows="2" placeholder="Delivery address">{{ $draft->delivery_address }}</x-ui.textarea>
                    <x-ui.input name="payment_method_preference" value="{{ $draft->payment_method_preference }}" placeholder="Payment method preference" required />
                    <h3 class="font-medium">Items</h3>
                    @foreach ($draft->items as $index => $item)
                        <div class="grid grid-cols-6 gap-2"><select name="items[{{ $index }}][product_color_size_id]" class="col-span-4 rounded-md border-gray-300 text-sm" required>@foreach ($cells as $cell)<option value="{{ $cell->id }}" @selected($cell->id === $item->product_color_size_id)>{{ $cell->color->product->name }} / {{ $cell->color->color->name }} / {{ $cell->size->size->name }} ({{ $cell->available_stock }} available)</option>@endforeach</select><input class="rounded-md border-gray-300 text-sm" type="number" min="1" name="items[{{ $index }}][quantity]" value="{{ $item->quantity }}" required><input class="rounded-md border-gray-300 text-sm" type="number" min="0" step="0.01" name="items[{{ $index }}][unit_price]" value="{{ $item->unit_price }}" aria-label="Unit price"></div>
                    @endforeach
                    <x-ui.button type="submit">Save Draft</x-ui.button>
                </form>
                <p class="mt-3 text-sm"><span class="text-gray-500">Status:</span> {{ str($draft->status)->headline() }}</p>
                @if ($draft->summary_text)<pre class="mt-4 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm">{{ $draft->summary_text }}</pre>@endif
                @can('create messenger orders')<div class="mt-4 flex flex-wrap gap-2"><form method="POST" action="{{ route('messenger.prepare-summary', $conversation) }}">@csrf <x-ui.button type="submit">Prepare Final Summary</x-ui.button></form>@if ($draft->status === 'awaiting_confirmation')<form method="POST" action="{{ route('messenger.confirm', $conversation) }}">@csrf <x-ui.button type="submit">Confirm Summary</x-ui.button></form>@endif @if ($draft->status === 'confirmed')<form method="POST" action="{{ route('messenger.create-order', $conversation) }}">@csrf <x-ui.button type="submit">Create Order</x-ui.button></form>@endif</div>@endcan
            @else <p class="text-sm text-gray-500">No order draft has been collected yet.</p> @endif
        </x-ui.page-card>
    </div>
</div>
@endsection
