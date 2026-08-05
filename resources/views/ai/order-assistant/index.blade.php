@extends('layouts.app')

@section('page-title', 'AI Order Assistant')

@push('scripts')
    @vite(['resources/js/ai-assistant.js'])
@endpush

@section('content')
    <x-ui.page-header title="AI Order Assistant">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('orders.index')">Customer Orders</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($connected)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            <p class="font-medium">No AI provider is connected.</p>
            <p class="mt-1">
                An admin or manager must connect OpenAI or Google Gemini before the assistant can analyze orders.
            </p>
            @can('manage integrations')
                <p class="mt-2">
                    Go to
                    <a href="{{ route('integrations.index') }}" class="font-medium underline">Integrations</a>
                    (sidebar) → Configure OpenAI or Google Gemini → paste API key → Test Connection → Save → Set as Default.
                </p>
            @else
                <p class="mt-2">Ask an admin to set this up under <span class="font-medium">Integrations</span> in the sidebar.</p>
            @endcan
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="space-y-4">
            <x-ui.page-card>
                <div class="space-y-2 p-3">
                    <x-ui.button type="button" id="new-draft-btn" class="w-full justify-center">New Draft</x-ui.button>
                    <p class="px-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-gray-400">Quick Actions</p>
                    <button type="button" id="paste-sample-btn" class="flex h-8 w-full items-center rounded-md px-2.5 text-left text-[13px] text-gray-600 hover:bg-gray-50">Paste Messenger Order</button>
                    <button type="button" id="clear-message-btn" class="flex h-8 w-full items-center rounded-md px-2.5 text-left text-[13px] text-gray-600 hover:bg-gray-50">Clear</button>
                </div>
            </x-ui.page-card>

            <x-ui.page-card>
                <div class="border-b border-gray-200 px-3 py-2">
                    <h2 class="text-[13px] font-semibold text-gray-900">Recent AI Drafts</h2>
                </div>
                <div id="recent-drafts-list" class="max-h-96 overflow-y-auto p-2">
                    <p class="px-2 py-3 text-[13px] text-gray-500">No drafts yet.</p>
                </div>
            </x-ui.page-card>
        </aside>

        <section class="space-y-4">
            <x-ui.page-card>
                <div class="space-y-4 p-4">
                    <div class="flex flex-wrap items-end gap-3">
                        @if ($connected && $currentProvider)
                            <p id="current-provider-label" class="text-[13px] text-gray-700">
                                <span class="font-medium text-gray-900">Current AI Provider:</span>
                                {{ $currentProvider['label'] }} ({{ $currentProvider['model'] }})
                            </p>
                        @endif

                        @if ($canManageIntegrations)
                            <div class="min-w-48">
                                <x-ui.label for="provider-select">Switch Provider</x-ui.label>
                                <x-ui.select id="provider-select">
                                    @foreach ($providers as $providerOption)
                                        @if ($providerOption['connected'])
                                            <option
                                                value="{{ $providerOption['provider'] }}"
                                                @selected($providerOption['is_default'] || ($currentProvider && $currentProvider['provider'] === $providerOption['provider']))
                                            >
                                                {{ $providerOption['label'] }} ({{ $providerOption['model'] }})
                                            </option>
                                        @endif
                                    @endforeach
                                </x-ui.select>
                            </div>
                        @endif
                    </div>

                    <div>
                        <x-ui.label for="raw-message">Customer Message</x-ui.label>
                        <x-ui.textarea id="raw-message" rows="8" placeholder="Paste customer message or Messenger conversation here..."></x-ui.textarea>
                    </div>

                    <div class="flex gap-2">
                        <x-ui.button type="button" id="analyze-btn" :disabled="! $connected">Analyze Conversation</x-ui.button>
                        <span id="analyze-loading" class="hidden self-center text-[13px] text-gray-500">Analyzing...</span>
                    </div>
                </div>
            </x-ui.page-card>

            <x-ui.page-card id="preview-card" class="hidden">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h2 class="text-[13px] font-semibold text-gray-900">Draft Preview</h2>
                    <p id="preview-confidence" class="mt-0.5 text-[12px] text-gray-500"></p>
                </div>

                <div class="space-y-4 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-ui.label for="preview-customer-id">Customer</x-ui.label>
                            <x-ui.select id="preview-customer-id">
                                <option value="">{{ $canManageCustomers ? '+ Create new customer' : 'Select existing customer...' }}</option>
                                @foreach ($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        data-name="{{ $customer->name }}"
                                        data-handle="{{ $customer->handle }}"
                                        data-contact="{{ $customer->contact }}"
                                        data-source="{{ $customer->source?->value }}"
                                        data-notes="{{ $customer->notes }}"
                                    >
                                        {{ $customer->name }}
                                        @if ($customer->handle) ({{ $customer->handle }}) @endif
                                    </option>
                                @endforeach
                            </x-ui.select>
                            <p id="preview-customer-hint" class="mt-1 text-[12px] text-gray-500">
                                @if ($canManageCustomers)
                                    Select an existing customer, or leave as “Create new” to add one from the details below.
                                @else
                                    Select an existing customer to link this order.
                                @endif
                            </p>
                        </div>
                        <div>
                            <x-ui.label for="preview-customer-name">Customer Name *</x-ui.label>
                            <x-ui.input id="preview-customer-name" type="text" placeholder="Customer name" />
                        </div>
                        <div>
                            <x-ui.label for="preview-customer-contact">Contact</x-ui.label>
                            <x-ui.input id="preview-customer-contact" type="text" placeholder="Phone or handle" />
                        </div>
                        <div>
                            <x-ui.label for="preview-customer-source">Source</x-ui.label>
                            <x-ui.select id="preview-customer-source">
                                @foreach ($customerSources as $source)
                                    <option value="{{ $source->value }}">{{ $source->icon() }} {{ $source->label() }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-ui.label for="preview-customer-notes">Notes</x-ui.label>
                            <x-ui.textarea id="preview-customer-notes" rows="2"></x-ui.textarea>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4">
                        <x-ui.label for="draft-order-image">Layout Image</x-ui.label>
                        <p class="mt-0.5 text-[12px] text-gray-500">Optional design layout for this order. Becomes layout v1 when converted.</p>
                        <div id="draft-image-preview" class="mt-3 hidden">
                            <img id="draft-image-preview-img" src="" alt="Layout preview" class="max-h-48 rounded-lg border border-gray-200 object-contain" />
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <input
                                id="draft-order-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block text-[13px] text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                            />
                            <x-ui.button type="button" variant="secondary" id="draft-image-remove" class="hidden">Remove</x-ui.button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table w-full">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Color</th>
                                    <th>Size</th>
                                    <th>Qty</th>
                                    <th>Available</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="preview-items-body"></tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="button" variant="secondary" id="save-draft-btn">Save Draft</x-ui.button>
                        <x-ui.button type="button" id="convert-draft-btn">Create Customer Order</x-ui.button>
                        <x-ui.button type="button" variant="danger" id="reject-draft-btn">Reject Draft</x-ui.button>
                    </div>
                </div>
            </x-ui.page-card>
        </section>
    </div>

    <div id="convert-confirm-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="convert-confirm-title">
        <div class="ui-modal-panel max-w-lg overflow-hidden">
            <div class="ui-modal-header">
                <h2 id="convert-confirm-title" class="text-[13px] font-semibold text-gray-900">Confirm Customer Order</h2>
                <p id="convert-confirm-customer" class="mt-0.5 text-[12px] text-gray-500"></p>
            </div>
            <div class="ui-modal-body">
                <div class="overflow-x-auto">
                    <table class="ui-table w-full">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody id="convert-confirm-items"></tbody>
                    </table>
                </div>
            </div>
            <div class="ui-modal-footer">
                <x-ui.button type="button" variant="secondary" data-close="convert-confirm-modal">Cancel</x-ui.button>
                <x-ui.button type="button" id="convert-confirm-btn">Confirm Create</x-ui.button>
            </div>
        </div>
    </div>

    <script>
        window.aiAssistantConfig = @json($assistantConfig);
    </script>
@endsection
