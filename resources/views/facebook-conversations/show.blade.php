@extends('layouts.app')

@section('title', 'Messenger Inbox')

@section('content')
@php
    $selectedConversationId = $selectedConversation?->id;
@endphp

<div class="flex h-full min-h-0 flex-col gap-4 overflow-hidden" data-messenger-inbox data-app-timezone="{{ config('app.timezone') }}">
    <x-ui.page-header title="Facebook Messenger" subtitle="AI and staff-managed customer conversations" />

    @can('view messenger conversations')
        <div class="flex justify-end">
            <form method="POST" action="{{ route('messenger.sync') }}" class="flex items-center gap-2" data-sync-form>
                @csrf
                <x-ui.button type="submit">Sync Messages</x-ui.button>
            </form>
        </div>
    @endcan

    <div class="flex min-h-0 flex-1 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[21rem_minmax(0,1fr)_19rem] lg:items-stretch">
            <aside class="flex min-h-0 flex-col overflow-hidden border-b border-gray-200 bg-gray-50/80 lg:border-b-0 lg:border-r">
                <div class="border-b border-gray-200 p-4">
                    <div class="flex items-center gap-2">
                        <input type="search" data-conversation-search class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-[13px] outline-none ring-0 focus:border-brand focus:ring-2 focus:ring-brand/20" placeholder="Search conversations">
                        <button type="button" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-[13px] font-medium text-gray-700">Manage</button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-[12px]">
                        <span class="rounded-full bg-brand-soft px-3 py-1 font-medium text-brand">All messages</span>
                        <span class="rounded-full bg-white px-3 py-1 text-gray-600">Unread</span>
                        <span class="rounded-full bg-white px-3 py-1 text-gray-600">Priority</span>
                        <span class="rounded-full bg-white px-3 py-1 text-gray-600">Follow up</span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto" data-conversation-list>
                    @forelse ($conversations as $conversation)
                        @php
                            $isSelected = $selectedConversationId === $conversation->id;
                            $conversationName = $conversation->customer?->name ?? $conversation->customer_name ?? $conversation->draft?->customer_name ?? 'Customer';
                            $conversationSearchText = strtolower(trim(sprintf(
                                '%s %s %s %s',
                                $conversation->psid,
                                $conversation->page->name,
                                $conversationName,
                                $conversation->assignedUser?->name ?? '',
                            )));
                        @endphp
                        <a
                            href="{{ route('messenger.show', $conversation) }}"
                            data-conversation-item
                            data-conversation-text="{{ $conversationSearchText }}"
                            class="{{ $isSelected ? 'border-l-4 border-brand bg-white shadow-sm' : 'border-l-4 border-transparent hover:bg-white/70' }} flex items-start gap-3 border-b border-gray-100 px-4 py-4 transition"
                        >
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">
                                {{ strtoupper(substr($conversationName, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-semibold text-gray-900">{{ $conversationName }}</p>
                                        <p class="truncate text-[12px] text-gray-500">{{ $conversation->page->name }} · {{ str($conversation->state)->headline() }}</p>
                                    </div>
                                    <div class="text-right text-[11px] text-gray-500">
                                        <p>{{ $conversation->last_inbound_at?->timezone(config('app.timezone'))->format('g:i A') ?? '—' }}</p>
                                        @if (($conversation->unread_message_count ?? 0) > 0)
                                            <span class="mt-1 inline-flex rounded-full bg-brand px-2 py-0.5 font-medium text-white">{{ $conversation->unread_message_count }}</span>
                                        @endif
                                    </div>
                                </div>
                                <p class="mt-1 truncate text-[12px] text-gray-600">
                                    {{ $conversation->draft?->summary_text ? str($conversation->draft->summary_text)->before("\n")->limit(80) : 'PSID '.$conversation->psid }}
                                </p>
                                <div class="mt-2 flex items-center gap-2 text-[11px] text-gray-500">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5">{{ ucfirst($conversation->control_mode) }}</span>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5">{{ str($conversation->state)->headline() }}</span>
                                </div>
                                @if ($conversation->tags->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($conversation->tags as $tag)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="p-6 text-sm text-gray-500">No Messenger conversations yet.</div>
                    @endforelse
                </div>
            </aside>

            <section class="flex min-h-0 min-w-0 flex-col overflow-hidden border-b border-gray-200 lg:border-b-0 lg:border-r">
                @if ($selectedConversation)
                    <div class="shrink-0 border-b border-gray-200 px-4 py-4" data-conversation-version="{{ $selectedConversation->updated_at?->toIso8601String() }}">
                        <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-soft text-base font-semibold text-brand">
                                {{ strtoupper(substr($selectedConversation->customer?->name ?? $selectedConversation->customer_name ?? $selectedConversation->draft?->customer_name ?? 'Customer', 0, 1)) }}
                            </div>
                            <div>
                                <h2 class="text-[15px] font-semibold text-gray-900" data-conversation-customer-name>{{ $selectedConversation->customer?->name ?? $selectedConversation->customer_name ?? $selectedConversation->draft?->customer_name ?? 'Customer' }}</h2>
                                <p class="text-[12px] text-gray-500"><span data-conversation-page-name>{{ $selectedConversation->page->name }}</span> · <span data-conversation-psid>{{ $selectedConversation->psid }}</span></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($selectedConversation->control_mode === 'ai')
                                @can('take over messenger conversations')
                                    <form method="POST" action="{{ route('messenger.take-over', $selectedConversation) }}">@csrf <x-ui.button type="submit">Take Over</x-ui.button></form>
                                @endcan
                            @else
                                @can('take over messenger conversations')
                                    <form method="POST" action="{{ route('messenger.return-to-ai', $selectedConversation) }}">@csrf <x-ui.button type="submit">Return to AI</x-ui.button></form>
                                @endcan
                            @endif
                        </div>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto bg-white px-4 py-5" data-message-scroll>
                        <div class="mx-auto flex max-w-4xl flex-col gap-3" data-message-list>
                            @php $previousMessageTime = null; @endphp
                            @forelse ($selectedConversation->messages as $message)
                                @php
                                    $messageTime = $message->getRawOriginal('sent_at')
                                        ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $message->getRawOriginal('sent_at'), 'UTC')->setTimezone('Asia/Manila')
                                        : null;
                                    $showMessageTime = $messageTime && (! $previousMessageTime || $messageTime->diffInMinutes($previousMessageTime) >= 5);
                                    $previousMessageTime = $messageTime ?: $previousMessageTime;
                                @endphp
                                @if ($showMessageTime)
                                    <div class="my-2 text-center text-[11px] font-medium text-gray-500" data-message-time>{{ $messageTime->format('D g:i A') }}</div>
                                @endif
                                <div class="flex {{ $message->direction === 'inbound' ? 'justify-start' : 'justify-end' }}" data-message-item data-message-id="{{ $message->id }}">
                                    <div class="max-w-[80%] rounded-2xl px-4 py-3 text-[13px] shadow-sm {{ $message->direction === 'inbound' ? 'bg-gray-100 text-gray-900' : 'bg-brand text-white' }}">
                                        <p class="mb-1 text-[11px] font-medium {{ $message->direction === 'inbound' ? 'text-gray-500' : 'text-brand-soft' }}">
                                            {{ ucfirst($message->sender_type) }}
                                        </p>
                                        <p class="whitespace-pre-wrap">{{ filled($message->body) ? $message->body : ($message->attachments ? 'Attachment' : '['.$message->message_type.']') }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-10 text-center text-sm text-gray-500">
                                    No messages yet.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-gray-200 bg-white p-4">
                        @if ($selectedConversation->control_mode === 'human')
                            @can('take over messenger conversations')
                                <form method="POST" action="{{ route('messenger.reply', $selectedConversation) }}" class="space-y-3">
                                    @csrf
                                    <x-ui.textarea name="message" rows="3" placeholder="Reply as staff" required />
                                    <div class="flex justify-end">
                                        <x-ui.button type="submit">Send Reply</x-ui.button>
                                    </div>
                                </form>
                            @endcan
                        @else
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                AI is currently replying. Take over to send staff messages.
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex min-h-0 flex-1 items-center justify-center p-10 text-sm text-gray-500">
                        Select a conversation to view the thread.
                    </div>
                @endif
            </section>

            <aside class="flex min-h-0 flex-col overflow-y-auto bg-gray-50/80">
                @if ($selectedConversation)
                    <div class="border-b border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-[15px] font-semibold text-gray-900" data-sidebar-customer-name>{{ $selectedConversation->customer?->name ?? $selectedConversation->customer_name ?? $selectedConversation->draft?->customer_name ?? 'Customer' }}</h3>
                                <p class="mt-1 text-[12px] text-gray-500" data-sidebar-psid>{{ $selectedConversation->psid }}</p>
                            </div>
                            <span class="rounded-full bg-white px-2 py-1 text-[11px] font-medium text-gray-600">{{ ucfirst($selectedConversation->control_mode) }}</span>
                        </div>
                        <div class="mt-4 space-y-2 text-[13px] text-gray-700">
                            <div><span class="text-gray-500">Page:</span> {{ $selectedConversation->page->name }}</div>
                            <div><span class="text-gray-500">Status:</span> {{ str($selectedConversation->state)->headline() }}</div>
                            <div><span class="text-gray-500">Assigned:</span> {{ $selectedConversation->assignedUser?->name ?? 'Unassigned' }}</div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 p-4">
                        <h4 class="mb-2 text-[13px] font-semibold text-gray-900">Tags</h4>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($selectedConversation->tags as $tag)
                                <form method="POST" action="{{ route('messenger.tags.destroy', [$selectedConversation, $tag]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[12px] font-medium text-gray-700 hover:bg-gray-200">
                                        {{ $tag->name }} ×
                                    </button>
                                </form>
                            @empty
                                <p class="text-[13px] text-gray-500">No tags yet.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('messenger.tags.store', $selectedConversation) }}" class="mt-3 flex gap-2">
                            @csrf
                            <input name="name" class="w-full rounded-md border-gray-300 text-sm" placeholder="Add tag">
                            <button type="submit" class="rounded-md bg-brand px-3 py-2 text-[12px] font-medium text-white">Add</button>
                        </form>
                        @if (! empty($tags) && $tags->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($tags->whereNotIn('id', $selectedConversation->tags->pluck('id')) as $tag)
                                    <form method="POST" action="{{ route('messenger.tags.store', $selectedConversation) }}">
                                        @csrf
                                        <input type="hidden" name="name" value="{{ $tag->name }}">
                                        <button type="submit" class="rounded-full border border-gray-200 px-3 py-1 text-[12px] text-gray-600">{{ $tag->name }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="border-b border-gray-200 p-4">
                        <h4 class="mb-2 text-[13px] font-semibold text-gray-900">Order status</h4>
                        @if ($selectedConversation->draft)
                            <div class="space-y-3 text-[13px] text-gray-700">
                                <div><span class="text-gray-500">Draft status:</span> <span data-draft-status>{{ str($selectedConversation->draft->status)->headline() }}</span></div>
                                <div><span class="text-gray-500">Fulfillment:</span> <span data-draft-fulfillment>{{ str($selectedConversation->draft->fulfillment_method ?? 'n/a')->headline() }}</span></div>
                                <div><span class="text-gray-500">Payment:</span> <span data-draft-payment>{{ $selectedConversation->draft->payment_method_preference ?? 'n/a' }}</span></div>
                                <div><span class="text-gray-500">Address:</span> <span data-draft-address>{{ $selectedConversation->draft->delivery_address ?? 'n/a' }}</span></div>
                            </div>
                        @else
                            <p class="text-[13px] text-gray-500">No order draft has been collected yet.</p>
                        @endif
                    </div>

                    <div class="border-b border-gray-200 p-4">
                        <h4 class="mb-2 text-[13px] font-semibold text-gray-900">AI order summary</h4>
                        @if ($selectedConversation->draft)
                            <div class="space-y-3">
                                @if ($selectedConversation->draft->summary_text)
                                    <pre class="max-h-44 overflow-auto whitespace-pre-wrap rounded-xl bg-white p-3 text-[12px] text-gray-700 shadow-sm" data-draft-summary>{{ $selectedConversation->draft->summary_text }}</pre>
                                @else
                                    <p class="text-[13px] text-gray-500" data-draft-summary-empty>Prepare a final summary to lock the order details before confirmation.</p>
                                @endif

                                @if ($selectedConversation->draft->items->isNotEmpty())
                                    <div class="space-y-2">
                                        @foreach ($selectedConversation->draft->items as $item)
                                            <div class="rounded-xl border border-gray-200 bg-white p-3 text-[12px] text-gray-700 shadow-sm">
                                                <div class="font-medium text-gray-900">{{ $item->cell->color->product->name }}</div>
                                                <div>{{ $item->cell->color->color->name }} / {{ $item->cell->size->size->name }}</div>
                                                <div>Qty {{ $item->quantity }} · {{ number_format((float) $item->unit_price, 2) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <p class="text-[13px] text-gray-500">No draft available yet.</p>
                        @endif
                    </div>

                    <div class="p-4">
                        @if ($selectedConversation->draft)
                            <form method="POST" action="{{ route('messenger.draft.update', $selectedConversation) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                <x-ui.input name="customer_name" value="{{ $selectedConversation->draft->customer_name }}" placeholder="Customer name" required />
                                <select name="fulfillment_method" class="w-full rounded-md border-gray-300 text-sm" required>
                                    <option value="">Delivery or pickup</option>
                                    <option value="delivery" @selected($selectedConversation->draft->fulfillment_method === 'delivery')>Delivery</option>
                                    <option value="pickup" @selected($selectedConversation->draft->fulfillment_method === 'pickup')>Pickup</option>
                                </select>
                                <x-ui.textarea name="delivery_address" rows="2" placeholder="Delivery address">{{ $selectedConversation->draft->delivery_address }}</x-ui.textarea>
                                <x-ui.input name="payment_method_preference" value="{{ $selectedConversation->draft->payment_method_preference }}" placeholder="Payment method preference" required />

                                <div class="space-y-2">
                                    <h5 class="text-[13px] font-semibold text-gray-900">Items</h5>
                                    @foreach ($selectedConversation->draft->items as $index => $item)
                                        <div class="grid grid-cols-6 gap-2">
                                            <select name="items[{{ $index }}][product_color_size_id]" class="col-span-4 rounded-md border-gray-300 text-sm" required>
                                                @foreach ($cells as $cell)
                                                    <option value="{{ $cell->id }}" @selected($cell->id === $item->product_color_size_id)>
                                                        {{ $cell->color->product->name }} / {{ $cell->color->color->name }} / {{ $cell->size->size->name }} ({{ $cell->available_stock }} available)
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input class="rounded-md border-gray-300 text-sm" type="number" min="1" name="items[{{ $index }}][quantity]" value="{{ $item->quantity }}" required>
                                            <input class="rounded-md border-gray-300 text-sm" type="number" min="0" step="0.01" name="items[{{ $index }}][unit_price]" value="{{ $item->unit_price }}" aria-label="Unit price">
                                        </div>
                                    @endforeach
                                </div>

                                <x-ui.button type="submit">Save Draft</x-ui.button>
                            </form>

                            @can('create messenger orders')
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('messenger.prepare-summary', $selectedConversation) }}">
                                        @csrf
                                        <x-ui.button type="submit">Prepare Final Summary</x-ui.button>
                                    </form>

                                    @if ($selectedConversation->draft->status === 'awaiting_confirmation')
                                        <form method="POST" action="{{ route('messenger.confirm', $selectedConversation) }}">
                                            @csrf
                                            <x-ui.button type="submit">Confirm Summary</x-ui.button>
                                        </form>
                                    @endif

                                    @if ($selectedConversation->draft->status === 'confirmed')
                                        <form method="POST" action="{{ route('messenger.create-order', $selectedConversation) }}">
                                            @csrf
                                            <x-ui.button type="submit">Create Order</x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        @endif
                    </div>
                @else
                    <div class="p-6 text-sm text-gray-500">Open a conversation to see the contact and order details.</div>
                @endif
            </aside>
        </div>
    </div>

    <div class="hidden" data-messenger-snapshot-url="{{ $selectedConversation ? route('messenger.snapshot', $selectedConversation) : '' }}"></div>
    <div class="hidden" data-selected-conversation-id="{{ $selectedConversationId }}"></div>
</div>

<div class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background-color: rgba(17, 24, 39, 0.58);" data-sync-modal aria-live="polite" role="dialog" aria-modal="true" aria-label="Syncing Messenger messages">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-black/10">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Syncing Messenger messages</h2>
                <p class="mt-1 text-sm text-gray-500" data-sync-status>Downloading the latest conversations…</p>
            </div>
            <span class="text-lg font-semibold text-brand" data-sync-percent>0%</span>
        </div>
        <div class="mt-5 h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full w-0 rounded-full bg-brand transition-[width] duration-300" data-sync-progress></div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Please keep this page open. It will refresh when synchronization is complete.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-sync-form]');
        const modal = document.querySelector('[data-sync-modal]');
        if (!form || !modal) return;
        form.addEventListener('submit', () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            let percent = 0;
            const percentNode = modal.querySelector('[data-sync-percent]');
            const progressNode = modal.querySelector('[data-sync-progress]');
            const statusNode = modal.querySelector('[data-sync-status]');
            const timer = window.setInterval(() => {
                percent = Math.min(percent + (percent < 70 ? 7 : 2), 95);
                percentNode.textContent = `${percent}%`;
                progressNode.style.width = `${percent}%`;
                if (percent >= 70) statusNode.textContent = 'Processing messages and updating conversations…';
            }, 350);
            window.addEventListener('pagehide', () => window.clearInterval(timer), { once: true });
        });
    })();
</script>
@endpush
