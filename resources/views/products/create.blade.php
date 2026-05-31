@extends('layouts.app')

@section('page-title', 'Create Product')

@section('content')
    <x-ui.page-header title="Create Product" />

    <x-ui.page-card>
        <form id="product-form" class="space-y-4 p-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-ui.label for="name">Name</x-ui.label>
                    <x-ui.input id="name" type="text" required />
                </div>
                <div>
                    <x-ui.label for="code">Code</x-ui.label>
                    <x-ui.input id="code" type="text" required maxlength="16" />
                    <p class="mt-1 text-[11px] text-gray-500">Prefix for color item codes (e.g. RJA-001).</p>
                </div>
                <div class="sm:col-span-2">
                    <x-ui.label for="description">Description</x-ui.label>
                    <x-ui.textarea id="description" rows="3" />
                </div>
                <div>
                    <x-ui.label for="status">Status</x-ui.label>
                    <x-ui.select id="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </x-ui.select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit">Save Product</x-ui.button>
                <x-ui.button variant="secondary" :href="route('products.index')">Cancel</x-ui.button>
            </div>
        </form>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const nameInput = document.getElementById('name');
    const codeInput = document.getElementById('code');
    let codeManuallyEdited = false;

    codeInput?.addEventListener('input', () => { codeManuallyEdited = true; });

    nameInput?.addEventListener('blur', async () => {
        if (codeManuallyEdited || !nameInput.value.trim()) return;
        try {
            const response = await fetch(`/products/preview-code?name=${encodeURIComponent(nameInput.value)}`);
            const data = await response.json();
            if (data.code) codeInput.value = data.code;
        } catch (_) {}
    });

    document.getElementById('product-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = {
            name: document.getElementById('name').value,
            code: document.getElementById('code').value,
            description: document.getElementById('description').value || null,
            status: document.getElementById('status').value,
        };

        try {
            const response = await postData('{{ route('products.store') }}', payload);
            showToast(response.message || 'Product created.');
            window.location.href = response.redirect;
        } catch (error) {
            showToast(error.message || 'Unable to save product.', 'error');
        }
    });
});
</script>
@endpush
