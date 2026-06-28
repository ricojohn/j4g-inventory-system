@extends('layouts.app')

@section('page-title', 'Integrations')

@section('content')
    <x-ui.page-header title="Integrations" />

    <x-ui.page-card class="mb-4">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Where to set up</h2>
            <p class="mt-0.5 text-[12px] text-gray-500">Connect OpenAI or Google Gemini so the AI Order Assistant can analyze customer messages.</p>
        </div>
        <div class="space-y-3 p-4 text-[13px] text-gray-700">
            <p>
                <span class="font-medium text-gray-900">In this app:</span>
                open <span class="font-medium text-gray-900">Integrations</span> from the sidebar (Admin / Manager only), click
                <span class="font-medium text-gray-900">Configure</span> on a provider card, then save your API key and model.
            </p>
            <p>
                <span class="font-medium text-gray-900">For staff using AI Order Assistant:</span>
                go to <a href="{{ route('ai.order-assistant.index') }}" class="font-medium text-gray-900 underline">AI Order Assistant</a>.
                The default connected provider is used automatically. Admins can switch the default provider here or from the assistant page.
            </p>
            <p class="text-[12px] text-gray-500">
                Tip: after saving, click <span class="font-medium">Test Connection</span>. If both providers are connected, use
                <span class="font-medium">Set as Default</span> on the one you want the assistant to use.
            </p>
        </div>
    </x-ui.page-card>

    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">OpenAI setup</h2>
            </div>
            <ol class="list-decimal space-y-2 px-4 py-3 pl-8 text-[13px] text-gray-700">
                <li>Sign in at <a href="https://platform.openai.com/signup" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 underline">platform.openai.com</a>.</li>
                <li>Open <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 underline">API Keys</a> and create a new secret key.</li>
                <li>On this page, click <span class="font-medium">Configure</span> on the OpenAI card.</li>
                <li>Paste the API key, choose a model (e.g. <span class="font-medium">gpt-4o-mini</span>), then <span class="font-medium">Test Connection</span> and <span class="font-medium">Save</span>.</li>
                <li>Optional: click <span class="font-medium">Set as Default</span> to make OpenAI the assistant provider.</li>
            </ol>
        </x-ui.page-card>

        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Google Gemini setup</h2>
            </div>
            <ol class="list-decimal space-y-2 px-4 py-3 pl-8 text-[13px] text-gray-700">
                <li>Sign in with your Google account at <a href="https://aistudio.google.com/" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 underline">Google AI Studio</a>.</li>
                <li>Open <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 underline">Get API key</a> and create a key for your project.</li>
                <li>On this page, click <span class="font-medium">Configure</span> on the Google Gemini card.</li>
                <li>Paste the API key, choose a model (e.g. <span class="font-medium">gemini-1.5-flash</span>), then <span class="font-medium">Test Connection</span> and <span class="font-medium">Save</span>.</li>
                <li>Optional: click <span class="font-medium">Set as Default</span> to make Gemini the assistant provider.</li>
            </ol>
        </x-ui.page-card>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($providers as $provider)
            <x-ui.page-card>
                <div class="flex items-start justify-between gap-3 p-4">
                    <div>
                        <h2 class="text-[13px] font-semibold text-gray-900">{{ $provider['label'] }}</h2>
                        <p class="mt-1 text-[12px] text-gray-500">
                            @if ($provider['provider'] === 'openai')
                                Power the AI Order Assistant with GPT models.
                            @else
                                Use Google Gemini models for order extraction.
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span
                            id="status-badge-{{ $provider['provider'] }}"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $provider['connected'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}"
                        >
                            {{ $provider['connected'] ? 'Connected' : 'Not Connected' }}
                        </span>
                        @if ($provider['is_default'])
                            <span
                                id="default-badge-{{ $provider['provider'] }}"
                                class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-800"
                            >
                                Default
                            </span>
                        @else
                            <span id="default-badge-{{ $provider['provider'] }}" class="hidden"></span>
                        @endif
                    </div>
                </div>
                <div class="space-y-2 border-t border-gray-200 px-4 py-3 text-[13px] text-gray-700">
                    <p><span class="font-medium text-gray-900">Model:</span> <span id="model-label-{{ $provider['provider'] }}">{{ $provider['model'] }}</span></p>
                    <p><span class="font-medium text-gray-900">API Key:</span> {{ $provider['connected'] ? '••••••••' : 'Not configured' }}</p>
                    <p><span class="font-medium text-gray-900">Connected:</span> <span id="connected-label-{{ $provider['provider'] }}">{{ $provider['connected_at'] ?? '—' }}</span></p>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-gray-200 px-4 py-3">
                    <x-ui.button type="button" class="configure-provider-btn" data-provider="{{ $provider['provider'] }}">Configure</x-ui.button>
                    @if ($provider['connected'] && ! $provider['is_default'])
                        <x-ui.button type="button" variant="secondary" class="set-default-btn" data-provider="{{ $provider['provider'] }}">Set as Default</x-ui.button>
                    @endif
                </div>
            </x-ui.page-card>
        @endforeach
    </div>

    <div id="provider-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel max-w-lg">
            <div class="ui-modal-header">
                <h2 id="provider-modal-title" class="text-[13px] font-semibold text-gray-900">Configure Integration</h2>
                <p class="mt-0.5 text-[13px] text-gray-500">Save your API key and default model.</p>
                <p id="provider-modal-help" class="mt-2 hidden rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-[12px] text-gray-600"></p>
            </div>
            <form id="provider-form" class="space-y-4 p-4">
                <input type="hidden" id="provider-key" value="">
                <div>
                    <x-ui.label for="provider-api-key">API Key</x-ui.label>
                    <x-ui.input id="provider-api-key" type="password" placeholder="Leave blank to keep current key." autocomplete="off" />
                </div>
                <div>
                    <x-ui.label for="provider-model">Default Model</x-ui.label>
                    <x-ui.select id="provider-model"></x-ui.select>
                </div>
            </form>
            <div class="ui-modal-footer">
                <x-ui.button type="button" variant="secondary" data-close="provider-modal">Cancel</x-ui.button>
                <x-ui.button type="button" variant="danger" id="disconnect-provider" class="hidden">Disconnect</x-ui.button>
                <x-ui.button type="button" variant="secondary" id="test-provider">Test Connection</x-ui.button>
                <x-ui.button type="button" id="save-provider">Save</x-ui.button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@php
    $integrationConfig = [
        'providers' => collect($providers)->keyBy('provider')->map(fn ($provider) => [
            'label' => $provider['label'],
            'connected' => $provider['connected'],
            'model' => $provider['model'],
            'is_default' => $provider['is_default'],
            'updateUrl' => route('integrations.update', $provider['provider']),
            'testUrl' => route('integrations.test', $provider['provider']),
            'disconnectUrl' => route('integrations.disconnect', $provider['provider']),
            'defaultUrl' => route('integrations.default', $provider['provider']),
            'models' => $providerModels[$provider['provider']] ?? [],
        ])->all(),
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($integrationConfig);
    const modal = document.getElementById('provider-modal');
    const closeOverlay = (overlay) => overlay.classList.add('hidden');
    let activeProvider = null;

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeOverlay(modal);
    });
    modal?.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => closeOverlay(modal));
    });

    const openModal = (providerKey) => {
        const provider = config.providers[providerKey];
        if (!provider) return;

        activeProvider = providerKey;
        document.getElementById('provider-key').value = providerKey;
        document.getElementById('provider-modal-title').textContent = `${provider.label} Integration`;
        document.getElementById('provider-api-key').value = '';

        const help = document.getElementById('provider-modal-help');
        if (help) {
            if (providerKey === 'openai') {
                help.innerHTML = 'Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer" class="font-medium underline">platform.openai.com/api-keys</a>. Paste it below, test, then save.';
                help.classList.remove('hidden');
            } else if (providerKey === 'gemini') {
                help.innerHTML = 'Get your API key from <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="font-medium underline">Google AI Studio</a>. Paste it below, test, then save.';
                help.classList.remove('hidden');
            } else {
                help.classList.add('hidden');
                help.textContent = '';
            }
        }

        const modelSelect = document.getElementById('provider-model');
        modelSelect.innerHTML = provider.models.map((model) => {
            const selected = model === provider.model ? 'selected' : '';
            return `<option value="${model}" ${selected}>${model}</option>`;
        }).join('');

        const disconnectBtn = document.getElementById('disconnect-provider');
        if (provider.connected) {
            disconnectBtn.classList.remove('hidden');
        } else {
            disconnectBtn.classList.add('hidden');
        }

        modal?.classList.remove('hidden');
    };

    document.querySelectorAll('.configure-provider-btn').forEach((button) => {
        button.addEventListener('click', () => openModal(button.dataset.provider));
    });

    document.querySelectorAll('.set-default-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const providerKey = button.dataset.provider;
            const provider = config.providers[providerKey];
            try {
                await postData(provider.defaultUrl, {});
                showToast(`${provider.label} is now the default AI provider.`);
                window.location.reload();
            } catch (error) {
                showToast(error.message || 'Unable to set default provider.', 'error');
            }
        });
    });

    const payload = () => ({
        api_key: document.getElementById('provider-api-key')?.value ?? '',
        model: document.getElementById('provider-model')?.value ?? '',
    });

    document.getElementById('test-provider')?.addEventListener('click', async () => {
        const button = document.getElementById('test-provider');
        const provider = config.providers[activeProvider];
        setButtonLoading(button, true, 'Testing...');
        try {
            const response = await postData(provider.testUrl, payload());
            showToast(response.message || 'Connection successful.');
        } catch (error) {
            showToast(error.message || 'Connection failed.', 'error');
        } finally {
            setButtonLoading(button, false);
        }
    });

    document.getElementById('save-provider')?.addEventListener('click', async () => {
        const button = document.getElementById('save-provider');
        const provider = config.providers[activeProvider];
        setButtonLoading(button, true, 'Saving...');
        try {
            const response = await postData(provider.updateUrl, payload(), 'PUT');
            document.getElementById(`status-badge-${activeProvider}`).textContent = 'Connected';
            document.getElementById(`status-badge-${activeProvider}`).className = 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-green-100 text-green-800';
            document.getElementById(`model-label-${activeProvider}`).textContent = response.model;
            document.getElementById(`connected-label-${activeProvider}`).textContent = response.connected_at ?? '—';
            document.getElementById('provider-api-key').value = '';
            closeOverlay(modal);
            showToast(response.message || 'Integration saved.');
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Unable to save integration.', 'error');
        } finally {
            setButtonLoading(button, false);
        }
    });

    document.getElementById('disconnect-provider')?.addEventListener('click', async () => {
        const provider = config.providers[activeProvider];
        if (!confirm(`Disconnect ${provider.label} integration?`)) return;
        try {
            await postData(provider.disconnectUrl, {}, 'DELETE');
            closeOverlay(modal);
            showToast(`${provider.label} disconnected.`);
            window.location.reload();
        } catch (error) {
            showToast(error.message || 'Unable to disconnect.', 'error');
        }
    });
});
</script>
@endpush
