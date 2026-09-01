@extends('layouts.app')

@section('title', 'Messenger Conversation')

@section('content')
<div class="space-y-4">
    <x-ui.page-header title="Messenger conversation" subtitle="{{ $conversation->page->name }} · {{ $conversation->psid }}" />

    <div class="flex flex-wrap gap-2">
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
                    <div class="rounded-lg p-3 {{ $message->direction === 'inbound' ? 'bg-gray-100' : 'bg-blue-50' }}">
                        <p class="text-xs text-gray-500">{{ ucfirst($message->sender_type) }} · {{ $message->created_at->format('M d, H:i') }}</p>
                        <p class="whitespace-pre-wrap text-sm">{{ $message->body ?: '['.$message->message_type.']' }}</p>
                    </div>
                @empty <p class="text-sm text-gray-500">No messages.</p> @endforelse
            </div>
            @if ($conversation->control_mode === 'human')
                @can('take over messenger conversations')
                    <form class="mt-4 space-y-2" method="POST" action="{{ route('messenger.reply', $conversation) }}">
                        @csrf
                        <x-ui.textarea name="message" rows="3" placeholder="Reply as staff" required />
                        <x-ui.button type="submit">Send Reply</x-ui.button>
                    </form>
                @endcan
            @endif
        </x-ui.page-card>

        <x-ui.page-card>
            <h2 class="mb-3 font-semibold">Order draft</h2>
            @if ($draft = $conversation->draft)
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Customer</dt><dd>{{ $draft->customer_name ?: 'Missing' }}</dd></div>
                    <div><dt class="text-gray-500">Fulfillment</dt><dd>{{ $draft->fulfillment_method ?: 'Missing' }}</dd></div>
                    <div><dt class="text-gray-500">Address</dt><dd>{{ $draft->delivery_address ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Payment preference</dt><dd>{{ $draft->payment_method_preference ?: 'Missing' }}</dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd>{{ str($draft->status)->headline() }}</dd></div>
                </dl>
                <h3 class="mt-4 font-medium">Items</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($draft->items as $item)<li>{{ $item->cell->color->product->name }} / {{ $item->cell->color->color->name }} / {{ $item->cell->size->size->name }} × {{ $item->quantity }}</li>@endforeach
                </ul>
                @if ($draft->summary_text)<pre class="mt-4 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm">{{ $draft->summary_text }}</pre>@endif
                @can('create messenger orders')
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('messenger.prepare-summary', $conversation) }}">@csrf <x-ui.button type="submit">Prepare Final Summary</x-ui.button></form>
                        @if ($draft->status === 'awaiting_confirmation')<form method="POST" action="{{ route('messenger.confirm', $conversation) }}">@csrf <x-ui.button type="submit">Confirm Summary</x-ui.button></form>@endif
                        @if ($draft->status === 'confirmed')<form method="POST" action="{{ route('messenger.create-order', $conversation) }}">@csrf <x-ui.button type="submit">Create Order</x-ui.button></form>@endif
                    </div>
                @endcan
            @else
                <p class="text-sm text-gray-500">No order draft has been collected yet.</p>
            @endif
        </x-ui.page-card>
    </div>
</div>
@endsection
