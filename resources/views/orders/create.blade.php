@extends('layouts.app')

@section('page-title', 'New Customer Order')

@section('content')
    <x-ui.page-header title="New Customer Order" />

    <form id="order-form" method="POST" action="{{ route('orders.store') }}" class="space-y-4">
        @csrf

        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Customer Information</h2>
            </div>
            <div class="space-y-4 p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-ui.label for="customer_name">Customer Name *</x-ui.label>
                        <x-ui.input id="customer_name" name="customer_name" type="text" required placeholder="e.g. Juan Dela Cruz" :value="old('customer_name')" />
                        @error('customer_name')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-ui.label for="customer_contact">Contact Number / Handle</x-ui.label>
                        <x-ui.input id="customer_contact" name="customer_contact" type="text" placeholder="+63 912 345 6789 or @username" :value="old('customer_contact')" />
                        <p class="mt-1 text-[11px] text-gray-500">Phone, Viber, or messenger username</p>
                        @error('customer_contact')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-ui.label for="customer_source">Source *</x-ui.label>
                        <x-ui.select id="customer_source" name="customer_source" required>
                            <option value="">-- Select source --</option>
                            @foreach ($customerSources as $source)
                                <option value="{{ $source->value }}" @selected(old('customer_source') === $source->value)>
                                    {{ $source->icon() }} {{ $source->label() }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        @error('customer_source')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <x-ui.label for="customer_notes">Customer Notes</x-ui.label>
                    <x-ui.textarea id="customer_notes" name="customer_notes" rows="3" placeholder="Delivery address, special requests, packaging preferences, etc.">{{ old('customer_notes') }}</x-ui.textarea>
                    @error('customer_notes')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-ui.page-card>

        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Order Items</h2>
            </div>
            <div class="space-y-4 p-4">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-40 flex-1">
                        <x-ui.label for="product-picker">Product</x-ui.label>
                        <x-ui.select id="product-picker">
                            <option value="">Select product...</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="min-w-32 flex-1">
                        <x-ui.label for="color-picker">Color</x-ui.label>
                        <x-ui.select id="color-picker" disabled>
                            <option value="">Select product first...</option>
                        </x-ui.select>
                    </div>
                    <div class="min-w-32 flex-1">
                        <x-ui.label for="size-picker">Size</x-ui.label>
                        <x-ui.select id="size-picker" disabled>
                            <option value="">Select color first...</option>
                        </x-ui.select>
                    </div>
                    <div class="w-24">
                        <x-ui.label for="line-qty">Qty</x-ui.label>
                        <x-ui.input id="line-qty" type="number" min="1" value="1" />
                    </div>
                    <x-ui.button type="button" variant="secondary" id="add-line-item">Add Item</x-ui.button>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table w-full">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Product</th>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Qty</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="line-items-body">
                            <tr id="line-items-empty">
                                <td colspan="6" class="text-center text-[13px] text-gray-500">No line items yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </x-ui.page-card>

        <p class="text-[12px] text-gray-500">
            ℹ️ Stock will be automatically reserved when this order is saved.
            If stock is short, a draft Purchase Order will be created automatically.
        </p>

        <div class="flex gap-2">
            <x-ui.button type="submit">Save Order</x-ui.button>
            <x-ui.button variant="secondary" :href="route('orders.index')">Cancel</x-ui.button>
        </div>
    </form>
@endsection

@push('scripts')
@php
    $formConfig = ['productCellsUrl' => route('orders.product-cells')];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($formConfig);
    const lineItems = [];
    let availableCells = [];

    const productPicker = document.getElementById('product-picker');
    const colorPicker = document.getElementById('color-picker');
    const sizePicker = document.getElementById('size-picker');
    const lineItemsBody = document.getElementById('line-items-body');
    const emptyRow = document.getElementById('line-items-empty');

    const resetColorSize = () => {
        colorPicker.innerHTML = '<option value="">Select product first...</option>';
        colorPicker.disabled = true;
        sizePicker.innerHTML = '<option value="">Select color first...</option>';
        sizePicker.disabled = true;
        availableCells = [];
    };

    const populateColors = () => {
        const colors = [...new Set(availableCells.map((cell) => cell.color_name))].sort();
        colorPicker.innerHTML = colors.length
            ? '<option value="">Select color...</option>' + colors.map((name) => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`).join('')
            : '<option value="">No colors available</option>';
        colorPicker.disabled = colors.length === 0;
        sizePicker.innerHTML = '<option value="">Select color first...</option>';
        sizePicker.disabled = true;
    };

    const populateSizes = (colorName) => {
        const sizes = availableCells.filter((cell) => cell.color_name === colorName);
        sizePicker.innerHTML = sizes.length
            ? '<option value="">Select size...</option>' + sizes.map((cell) =>
                `<option value="${cell.cell_id}">${escapeHtml(cell.size_name)} (${escapeHtml(cell.available_stock)} avail.)</option>`
            ).join('')
            : '<option value="">No sizes available</option>';
        sizePicker.disabled = sizes.length === 0;
    };

    productPicker?.addEventListener('change', async () => {
        const productId = productPicker.value;
        resetColorSize();

        if (!productId) return;

        colorPicker.innerHTML = '<option value="">Loading...</option>';

        try {
            const response = await fetch(`${config.productCellsUrl}?product_id=${encodeURIComponent(productId)}`);
            const data = await response.json();
            availableCells = data.cells ?? [];
            populateColors();
        } catch (_) {
            colorPicker.innerHTML = '<option value="">Unable to load colors</option>';
        }
    });

    colorPicker?.addEventListener('change', () => {
        if (!colorPicker.value) {
            sizePicker.innerHTML = '<option value="">Select color first...</option>';
            sizePicker.disabled = true;
            return;
        }
        populateSizes(colorPicker.value);
    });

    const renderLineItems = () => {
        if (lineItems.length === 0) {
            emptyRow.style.display = '';
            lineItemsBody.querySelectorAll('tr:not(#line-items-empty)').forEach((row) => row.remove());
            return;
        }

        emptyRow.style.display = 'none';
        lineItemsBody.querySelectorAll('tr:not(#line-items-empty)').forEach((row) => row.remove());

        lineItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(item.item_code)}</td>
                <td>${escapeHtml(item.product_name)}</td>
                <td>${escapeHtml(item.color_name)}</td>
                <td>${escapeHtml(item.size_name)}</td>
                <td>
                    <input type="hidden" name="items[${index}][product_color_size_id]" value="${item.cell_id}" />
                    <input type="number" name="items[${index}][quantity_ordered]" value="${item.quantity}" min="1" class="ui-input w-20" required />
                </td>
                <td><button type="button" class="ui-row-action ui-row-action-danger remove-line" data-index="${index}">Remove</button></td>
            `;
            lineItemsBody.appendChild(row);
        });
    };

    document.getElementById('add-line-item')?.addEventListener('click', () => {
        const cellId = Number(sizePicker?.value);
        const qty = Number(document.getElementById('line-qty')?.value ?? 1);
        const cell = availableCells.find((entry) => entry.cell_id === cellId);

        if (!cell || qty < 1) {
            showToast('Select product, color, size, and quantity.', 'error');
            return;
        }

        if (lineItems.some((item) => item.cell_id === cellId)) {
            showToast('This item is already on the order.', 'error');
            return;
        }

        lineItems.push({
            cell_id: cellId,
            item_code: cell.item_code,
            product_name: cell.product_name,
            color_name: cell.color_name,
            size_name: cell.size_name,
            quantity: qty,
        });

        renderLineItems();
    });

    lineItemsBody?.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-line');
        if (!button) return;
        lineItems.splice(Number(button.dataset.index), 1);
        renderLineItems();
    });

    document.getElementById('order-form')?.addEventListener('submit', (event) => {
        if (lineItems.length === 0) {
            event.preventDefault();
            showToast('Add at least one line item.', 'error');
        }
    });
});
</script>
@endpush
