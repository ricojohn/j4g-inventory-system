@extends('layouts.app')

@section('page-title', 'AI Assistance')

@push('scripts')
    @vite(['resources/js/ai-assistance.js'])
@endpush

@section('content')
    <x-ui.page-header title="AI Assistance">
        <x-slot:actions>
            @can('manage integrations')
                <x-ui.button variant="secondary" :href="route('integrations.index')">Integrations</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($connected)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            <p class="font-medium">No AI provider is connected.</p>
            <p class="mt-1">
                An admin or manager must connect OpenAI or Google Gemini before AI Assistance can answer questions or generate summaries.
            </p>
            @can('manage integrations')
                <p class="mt-2">
                    Go to
                    <a href="{{ route('integrations.index') }}" class="font-medium underline">Integrations</a>
                    → Configure OpenAI or Google Gemini → paste API key → Test Connection → Save → Set as Default.
                </p>
            @else
                <p class="mt-2">Ask an admin to set this up under <span class="font-medium">Integrations</span> in the sidebar.</p>
            @endcan
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
        <aside class="space-y-4">
            <x-ui.page-card>
                <div class="space-y-2 p-3">
                    <x-ui.button type="button" id="new-chat-btn" class="w-full justify-center" :disabled="! $connected">New Chat</x-ui.button>
                    <p class="px-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-gray-400">Suggested Reports</p>
                    <div id="suggestion-list" class="space-y-1">
                        @foreach ($assistanceConfig['suggestions'] as $suggestion)
                            <button
                                type="button"
                                class="suggestion-chip flex w-full items-start rounded-md px-2.5 py-2 text-left text-[13px] text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                data-prompt="{{ $suggestion }}"
                                @disabled(! $connected)
                            >
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </x-ui.page-card>

            @if ($connected && $currentProvider)
                <x-ui.page-card>
                    <div class="space-y-1 p-3 text-[13px] text-gray-700">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Provider</p>
                        <p class="font-medium text-gray-900">{{ $currentProvider['label'] }}</p>
                        <p class="text-gray-500">{{ $currentProvider['model'] }}</p>
                    </div>
                </x-ui.page-card>
            @endif
        </aside>

        <section>
            <x-ui.page-card>
                <div class="flex h-[min(70vh,720px)] flex-col">
                    <div id="chat-messages" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                        <div class="rounded-lg bg-gray-50 px-3 py-2 text-[13px] text-gray-600" data-role="system-intro">
                            Ask about inventory, orders, finance, production, suppliers, or customers. Answers stay grounded in live system data.
                        </div>
                    </div>

                    <div class="border-t border-gray-200 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <div class="min-w-0 flex-1">
                                <x-ui.label for="chat-input">Message</x-ui.label>
                                <x-ui.textarea
                                    id="chat-input"
                                    rows="3"
                                    placeholder="Ask a question or request a summary..."
                                    :disabled="! $connected"
                                ></x-ui.textarea>
                            </div>
                            <x-ui.button type="button" id="send-btn" :disabled="! $connected">Send</x-ui.button>
                        </div>
                        <p id="chat-status" class="mt-2 hidden text-[12px] text-gray-500">Thinking...</p>
                        <p id="chat-error" class="mt-2 hidden text-[12px] text-red-600"></p>
                    </div>
                </div>
            </x-ui.page-card>
        </section>
    </div>

    <script>
        window.assistanceConfig = @json($assistanceConfig);
    </script>
@endsection
