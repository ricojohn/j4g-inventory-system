@extends('layouts.app')

@section('page-title', 'New Purchase Order')

@section('content')
    <x-ui.page-header title="New Purchase Order" />

    @if (session('shortage_notice'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            ℹ️ {{ session('shortage_notice') }}
        </div>
    @elseif ($fromOrder)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            ℹ️ This PO was pre-filled from shortage on <strong>{{ $fromOrder->order_number }}</strong>
        </div>
    @endif

    <form id="supplier-order-form" method="POST" action="{{ route('supplier-orders.store') }}" class="space-y-4">
        @csrf

        <x-ui.page-card>
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Supplier Information</h2>
            </div>
            <div class="space-y-4 p-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-ui.label for="supplier_id">Supplier</x-ui.label>
                        <x-ui.select id="supplier_id" name="supplier_id">
                            <option value="">-- Select supplier --</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </x-ui.select>
                        @error('supplier_id')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <x-ui.label for="remarks">Remarks</x-ui.label>
                        <x-ui.textarea id="remarks" name="remarks" rows="2">{{ old('remarks') }}</x-ui.textarea>
                        @error('remarks')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                    </div>
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

        @if ($fromOrder)
            <input type="hidden" name="from_order_id" value="{{ $fromOrder->id }}" />
        @endif

        <x-ui.page-card id="po-review-card">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-[13px] font-semibold text-gray-900">Review Purchase Order</h2>
                <p class="mt-0.5 text-[12px] text-gray-500">Confirm details below before saving.</p>
            </div>
            <div class="space-y-4 p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500">Supplier</p>
                        <p id="review-supplier" class="mt-1 text-[13px] text-gray-900">—</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Linked Order</p>
                        <p id="review-linked-order" class="mt-1 text-[13px] text-gray-900">{{ $fromOrder?->order_number ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Remarks</p>
                        <p id="review-remarks" class="mt-1 text-[13px] text-gray-900">—</p>
                    </div>
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
                            </tr>
                        </thead>
                        <tbody id="review-items-body">
                            <tr id="review-items-empty">
                                <td colspan="5" class="text-center text-[13px] text-gray-500">No items to review yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex gap-6 text-[13px] text-gray-700">
                    <p><span class="font-medium">Items:</span> <span id="review-item-count">0</span></p>
                    <p><span class="font-medium">Total Qty:</span> <span id="review-total-qty">0</span></p>
                </div>
            </div>
        </x-ui.page-card>

        <div class="flex gap-2">
            <x-ui.button type="submit">Create PO</x-ui.button>
            <x-ui.button variant="secondary" :href="route('supplier-orders.index')">Cancel</x-ui.button>
        </div>
    </form>
@endsection

@push('scripts')
@php
    $formConfig = [
        'productCellsUrl' => route('supplier-orders.product-cells'),
        'prefillItems' => $prefillItems,
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($formConfig);
    const lineItems = [...(config.prefillItems ?? [])].map((item) => ({
        cell_id: item.product_color_size_id,
        customer_order_item_id: item.customer_order_item_id ?? null,
        item_code: item.item_code,
        product_name: item.product_name,
        color_name: item.color_name,
        size_name: item.size_name,
        quantity: item.quantity_ordered,
    }));
    let availableCells = [];

    const productPicker = document.getElementById('product-picker');
    const colorPicker = document.getElementById('color-picker');
    const sizePicker = document.getElementById('size-picker');
    const lineItemsBody = document.getElementById('line-items-body');
    const emptyRow = document.getElementById('line-items-empty');
    const supplierSelect = document.getElementById('supplier_id');
    const remarksInput = document.getElementById('remarks');
    const reviewItemsBody = document.getElementById('review-items-body');
    const reviewItemsEmpty = document.getElementById('review-items-empty');

    const syncLineItemQuantities = () => {
        lineItemsBody?.querySelectorAll('tr:not(#line-items-empty)').forEach((row, index) => {
            const qtyInput = row.querySelector('input[name*="[quantity_ordered]"]');
            if (qtyInput && lineItems[index]) {
                lineItems[index].quantity = Number(qtyInput.value ?? lineItems[index].quantity);
            }
        });
    };

    const renderReview = () => {
        syncLineItemQuantities();

        const supplierOption = supplierSelect?.selectedOptions?.[0];
        const supplierLabel = supplierSelect?.value
            ? (supplierOption?.textContent?.trim() || '—')
            : 'No supplier selected';

        document.getElementById('review-supplier').textContent = supplierLabel;
        document.getElementById('review-remarks').textContent = remarksInput?.value?.trim() || '—';
        document.getElementById('review-item-count').textContent = String(lineItems.length);
        document.getElementById('review-total-qty').textContent = String(
            lineItems.reduce((sum, item) => sum + Number(item.quantity ?? 0), 0)
        );

        if (lineItems.length === 0) {
            reviewItemsEmpty.style.display = '';
            reviewItemsBody.querySelectorAll('tr:not(#review-items-empty)').forEach((row) => row.remove());
            return;
        }

        reviewItemsEmpty.style.display = 'none';
        reviewItemsBody.querySelectorAll('tr:not(#review-items-empty)').forEach((row) => row.remove());

        lineItems.forEach((item) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(item.item_code)}</td>
                <td>${escapeHtml(item.product_name)}</td>
                <td>${escapeHtml(item.color_name)}</td>
                <td>${escapeHtml(item.size_name)}</td>
                <td>${escapeHtml(item.quantity)}</td>
            `;
            reviewItemsBody.appendChild(row);
        });
    };

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
                `<option value="${cell.cell_id}">${escapeHtml(cell.size_name)}</option>`
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
                    ${item.customer_order_item_id ? `<input type="hidden" name="items[${index}][customer_order_item_id]" value="${item.customer_order_item_id}" />` : ''}
                    <input type="number" name="items[${index}][quantity_ordered]" value="${item.quantity}" min="1" class="ui-input w-20" required />
                </td>
                <td><button type="button" class="ui-row-action ui-row-action-danger remove-line" data-index="${index}">Remove</button></td>
            `;
            lineItemsBody.appendChild(row);
        });

        renderReview();
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
            showToast('This item is already on the PO.', 'error');
            return;
        }

        lineItems.push({
            cell_id: cellId,
            customer_order_item_id: null,
            item_code: cell.item_code,
            product_name: cell.product_name,
            color_name: cell.color_name,
            size_name: cell.size_name,
            quantity: qty,
        });

        renderLineItems();
    });

    lineItemsBody?.addEventListener('input', (event) => {
        if (event.target.matches('input[name*="[quantity_ordered]"]')) {
            renderReview();
        }
    });

    lineItemsBody?.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-line');
        if (!button) return;
        lineItems.splice(Number(button.dataset.index), 1);
        renderLineItems();
    });

    supplierSelect?.addEventListener('change', renderReview);
    remarksInput?.addEventListener('input', renderReview);

    document.getElementById('supplier-order-form')?.addEventListener('submit', (event) => {
        if (lineItems.length === 0) {
            event.preventDefault();
            showToast('Add at least one line item.', 'error');
        }
    });

    renderLineItems();
    renderReview();
});
</script>
@endpush
