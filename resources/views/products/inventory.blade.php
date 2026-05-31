@extends('layouts.app')

@section('page-title', 'Manage Inventory')

@section('content')
    <x-ui.page-header :title="$product->name" :subtitle="$product->item_code . ' · ' . $product->category->name . ' · ' . $product->color">
        <x-slot:actions>
            @canany(['stock in', 'stock out', 'reserve stock', 'release stock', 'damage stock', 'adjust stock'])
                <x-ui.button type="button" variant="secondary" id="open-bulk-inventory-modal">Bulk Update</x-ui.button>
            @endcanany
            <x-ui.button variant="secondary" :href="route('products.index')">Back to Products</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="inventory-search" placeholder="Search sizes..." class="!w-auto min-w-48" />
                <x-ui.select id="inventory-per-page" class="!w-auto min-w-28">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.table-wrap>
            <x-ui.async-table tbody-id="product-inventory-table-body" pagination-id="product-inventory-pagination">
                <x-slot:head>
                    <tr>
                        <th>Size</th>
                        <th>Stock</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </x-slot:head>
            </x-ui.async-table>
        </x-ui.table-wrap>
    </x-ui.page-card>

    <div id="inventory-modal" class="ui-modal-overlay hidden">
        <div class="ui-modal-panel max-w-md">
            <div class="ui-modal-header">
                <h2 id="inventory-modal-title" class="text-[13px] font-semibold text-gray-900">Stock Action</h2>
                <p id="inventory-modal-description" class="mt-0.5 text-[13px] text-gray-500">Enter the quantity and optional remarks.</p>
            </div>
            <form id="inventory-form">
                <div class="ui-modal-body space-y-4">
                    <input type="hidden" id="inventory-variant-id">
                    <input type="hidden" id="inventory-action-type">
                    <div id="quantity-field">
                        <x-ui.label for="inventory-quantity">Quantity</x-ui.label>
                        <x-ui.input id="inventory-quantity" type="number" min="1" />
                    </div>
                    <div id="new-quantity-field" class="hidden">
                        <x-ui.label for="inventory-new-quantity">New Quantity</x-ui.label>
                        <x-ui.input id="inventory-new-quantity" type="number" min="0" />
                    </div>
                    <div>
                        <x-ui.label for="inventory-remarks">Remarks</x-ui.label>
                        <x-ui.textarea id="inventory-remarks" rows="3"></x-ui.textarea>
                    </div>
                </div>
                <div class="ui-modal-footer">
                    <x-ui.button variant="secondary" type="button" id="close-inventory-modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" id="inventory-submit">Submit</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <div id="bulk-inventory-modal" class="ui-modal-overlay hidden">
        <div class="ui-modal-panel max-w-2xl">
            <div class="ui-modal-header">
                <h2 class="text-[13px] font-semibold text-gray-900">Bulk Update</h2>
                <p class="mt-0.5 text-[13px] text-gray-500">{{ $product->name }}</p>
                <p id="bulk-inventory-status" class="mt-1 hidden text-[13px] text-amber-800"></p>
            </div>
            <form id="bulk-inventory-form">
                <div class="ui-modal-body space-y-4">
                    <div>
                        <x-ui.label for="bulk-inventory-action">Action</x-ui.label>
                        <x-ui.select id="bulk-inventory-action" class="mt-1"></x-ui.select>
                    </div>
                    <div>
                        <x-ui.label for="bulk-inventory-remarks">Remarks</x-ui.label>
                        <x-ui.textarea id="bulk-inventory-remarks" rows="2" class="mt-1"></x-ui.textarea>
                    </div>
                    <x-ui.table-wrap>
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Current</th>
                                    <th id="bulk-inventory-qty-header">Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="bulk-inventory-rows"></tbody>
                        </table>
                    </x-ui.table-wrap>
                </div>
                <div class="ui-modal-footer">
                    <x-ui.button variant="secondary" type="button" id="close-bulk-inventory-modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" id="bulk-inventory-submit">Save bulk</x-ui.button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
@php
    $tableConfig = [
        'productId' => $product->id,
        'dataUrl' => route('products.inventory.data', $product),
        'bulkUrl' => route('inventory.bulk'),
        'permissions' => [
            'canStockIn' => auth()->user()?->can('stock in'),
            'canStockOut' => auth()->user()?->can('stock out'),
            'canReserve' => auth()->user()?->can('reserve stock'),
            'canRelease' => auth()->user()?->can('release stock'),
            'canDamage' => auth()->user()?->can('damage stock'),
            'canAdjust' => auth()->user()?->can('adjust stock'),
        ],
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.tableConfig = @json($tableConfig);

    const permissions = window.tableConfig.permissions;
    const modal = document.getElementById('inventory-modal');
    const form = document.getElementById('inventory-form');
    const submitButton = document.getElementById('inventory-submit');
    const tableBody = document.getElementById('product-inventory-table-body');
    const bulkModal = document.getElementById('bulk-inventory-modal');
    const bulkForm = document.getElementById('bulk-inventory-form');
    const bulkSubmitButton = document.getElementById('bulk-inventory-submit');
    const bulkRowsBody = document.getElementById('bulk-inventory-rows');
    const bulkActionSelect = document.getElementById('bulk-inventory-action');
    const bulkRemarksInput = document.getElementById('bulk-inventory-remarks');
    const bulkQtyHeader = document.getElementById('bulk-inventory-qty-header');
    const bulkStatus = document.getElementById('bulk-inventory-status');
    let bulkVariants = [];

    const routes = {
        'stock-in': @json(route('inventory.stock-in')),
        'stock-out': @json(route('inventory.stock-out')),
        'reserve': @json(route('inventory.reserve')),
        'release': @json(route('inventory.release')),
        'damage': @json(route('inventory.damage')),
        'adjust': @json(route('inventory.adjust')),
    };
    const titles = {
        'stock-in': 'Stock In',
        'stock-out': 'Stock Out',
        'reserve': 'Reserve Stock',
        'release': 'Release Stock',
        'damage': 'Mark Damaged',
        'adjust': 'Adjust Stock',
    };
    const bulkActionOptions = [
        { value: 'stock-in', label: 'Stock In', allowed: permissions.canStockIn },
        { value: 'stock-out', label: 'Stock Out', allowed: permissions.canStockOut },
        { value: 'reserve', label: 'Reserve', allowed: permissions.canReserve },
        { value: 'release', label: 'Release', allowed: permissions.canRelease },
        { value: 'damage', label: 'Damage', allowed: permissions.canDamage },
        { value: 'adjust', label: 'Adjust', allowed: permissions.canAdjust },
    ].filter((option) => option.allowed);

    const table = initAsyncTable({
        tbodyId: 'product-inventory-table-body',
        paginationId: 'product-inventory-pagination',
        dataUrl: window.tableConfig.dataUrl,
        columnCount: 6,
        emptyMessage: 'No variants found.',
        getParams: () => ({
            search: document.getElementById('inventory-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('inventory-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((variant) => `
            <tr data-variant-id="${variant.id}">
                <td>${escapeHtml(variant.size_name)}</td>
                <td data-stock-quantity>${variant.stock_quantity}</td>
                <td data-reserved-quantity>${variant.reserved_quantity}</td>
                <td data-available-stock>${variant.available_stock}</td>
                <td>${renderStockBadge(variant.status, variant.status_label)}</td>
                <td>
                    <div class="flex flex-wrap items-center gap-1">
                        ${permissions.canStockIn ? `<button type="button" class="inventory-action ui-row-action border border-gray-300 bg-white" data-action="stock-in" data-variant-id="${variant.id}">In</button>` : ''}
                        ${permissions.canStockOut ? `<button type="button" class="inventory-action ui-row-action border border-gray-300 bg-white" data-action="stock-out" data-variant-id="${variant.id}">Out</button>` : ''}
                        ${permissions.canReserve ? `<button type="button" class="inventory-action ui-row-action border border-gray-300 bg-white" data-action="reserve" data-variant-id="${variant.id}">Reserve</button>` : ''}
                        ${permissions.canRelease ? `<button type="button" class="inventory-action ui-row-action border border-gray-300 bg-white" data-action="release" data-variant-id="${variant.id}">Release</button>` : ''}
                        ${permissions.canDamage ? `<button type="button" class="inventory-action ui-row-action border border-gray-300 bg-white" data-action="damage" data-variant-id="${variant.id}">Damage</button>` : ''}
                        ${permissions.canAdjust ? `<button type="button" class="inventory-action ui-row-action border border-gray-300 bg-white" data-action="adjust" data-variant-id="${variant.id}">Adjust</button>` : ''}
                    </div>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('inventory-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('inventory-per-page')?.addEventListener('change', () => table.loadData(1));

    const showModal = () => modal?.classList.remove('hidden');
    const hideModal = () => modal?.classList.add('hidden');
    const showBulkModal = () => bulkModal?.classList.remove('hidden');
    const hideBulkModal = () => bulkModal?.classList.add('hidden');

    function buildBulkActionSelect() {
        if (!bulkActionSelect) {
            return;
        }

        bulkActionSelect.innerHTML = bulkActionOptions
            .map((option) => `<option value="${option.value}">${escapeHtml(option.label)}</option>`)
            .join('');
    }

    function updateBulkQtyHeader(action) {
        if (!bulkQtyHeader) {
            return;
        }

        bulkQtyHeader.textContent = action === 'adjust' ? 'New Quantity' : 'Quantity';
    }

    function renderBulkRows(variants) {
        if (!bulkRowsBody) {
            return;
        }

        const action = bulkActionSelect?.value ?? 'stock-in';
        const min = action === 'adjust' ? 0 : 1;

        bulkRowsBody.innerHTML = variants.map((variant) => `
            <tr data-bulk-variant-id="${variant.id}" class="border-b border-gray-100">
                <td>${escapeHtml(variant.size_name)}</td>
                <td data-bulk-current>${variant.stock_quantity}</td>
                <td>
                    <input
                        type="number"
                        min="${min}"
                        data-bulk-qty
                        class="w-24 rounded-md border border-gray-300 px-2 py-1 text-[13px]"
                    />
                    <p data-bulk-error class="mt-1 hidden text-[11px] text-red-600"></p>
                </td>
            </tr>
        `).join('');
    }

    async function openBulkModal() {
        if (bulkActionOptions.length === 0) {
            showToast('You do not have permission to perform bulk inventory updates.', 'error');
            return;
        }

        buildBulkActionSelect();
        bulkRemarksInput.value = '';
        bulkStatus?.classList.add('hidden');
        bulkStatus.textContent = '';

        try {
            const payload = await fetchTableData(window.tableConfig.dataUrl, { per_page: 100 });
            bulkVariants = payload.data ?? [];
            renderBulkRows(bulkVariants);
            updateBulkQtyHeader(bulkActionSelect.value);
            showBulkModal();
        } catch (error) {
            showToast(error.message || 'Unable to load variants for bulk update.', 'error');
        }
    }

    function collectBulkItems(action) {
        const items = [];

        bulkRowsBody?.querySelectorAll('tr[data-bulk-variant-id]').forEach((row) => {
            const input = row.querySelector('[data-bulk-qty]');
            const errorEl = row.querySelector('[data-bulk-error]');
            const rawValue = input?.value?.trim() ?? '';

            row.classList.remove('bg-red-50');
            errorEl?.classList.add('hidden');
            errorEl.textContent = '';

            if (rawValue === '') {
                return;
            }

            const numericValue = Number(rawValue);

            if (action === 'adjust') {
                items.push({
                    product_variant_id: Number(row.dataset.bulkVariantId),
                    new_quantity: numericValue,
                });
            } else if (numericValue >= 1) {
                items.push({
                    product_variant_id: Number(row.dataset.bulkVariantId),
                    quantity: numericValue,
                });
            }
        });

        return items;
    }

    function applyBulkResults(results) {
        let successCount = 0;

        results.forEach((result) => {
            const row = bulkRowsBody?.querySelector(`tr[data-bulk-variant-id="${result.variant_id}"]`);
            const errorEl = row?.querySelector('[data-bulk-error]');
            const input = row?.querySelector('[data-bulk-qty]');

            if (result.success) {
                successCount++;
                row?.classList.remove('bg-red-50');
                errorEl?.classList.add('hidden');
                errorEl.textContent = '';
                if (input) {
                    input.value = '';
                }
                if (result.data) {
                    updateVariantRow(result.data);
                }
            } else {
                row?.classList.add('bg-red-50');
                if (errorEl) {
                    errorEl.textContent = result.message || 'Unable to update stock.';
                    errorEl.classList.remove('hidden');
                }
            }
        });

        return successCount;
    }

    function openInventoryAction(action, variantId) {
        document.getElementById('inventory-variant-id').value = variantId;
        document.getElementById('inventory-action-type').value = action;
        document.getElementById('inventory-modal-title').textContent = titles[action];
        document.getElementById('inventory-modal-description').textContent = action === 'adjust'
            ? 'Set the new total stock quantity for this variant.'
            : 'Enter the quantity and optional remarks.';
        document.getElementById('quantity-field').classList.toggle('hidden', action === 'adjust');
        document.getElementById('new-quantity-field').classList.toggle('hidden', action !== 'adjust');
        form.reset();
        document.getElementById('inventory-variant-id').value = variantId;
        document.getElementById('inventory-action-type').value = action;
        showModal();
    }

    tableBody?.addEventListener('click', (event) => {
        const button = event.target.closest('.inventory-action');
        if (button) {
            openInventoryAction(button.dataset.action, button.dataset.variantId);
        }
    });

    document.getElementById('close-inventory-modal')?.addEventListener('click', hideModal);
    document.getElementById('open-bulk-inventory-modal')?.addEventListener('click', openBulkModal);
    document.getElementById('close-bulk-inventory-modal')?.addEventListener('click', hideBulkModal);

    bulkActionSelect?.addEventListener('change', () => {
        updateBulkQtyHeader(bulkActionSelect.value);
        renderBulkRows(bulkVariants);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const action = document.getElementById('inventory-action-type').value;
        const variantId = Number(document.getElementById('inventory-variant-id').value);
        const remarks = document.getElementById('inventory-remarks').value;

        let payload;
        if (action === 'adjust') {
            payload = {
                product_variant_id: variantId,
                new_quantity: Number(document.getElementById('inventory-new-quantity').value),
                remarks,
            };
        } else {
            payload = {
                product_variant_id: variantId,
                quantity: Number(document.getElementById('inventory-quantity').value),
                remarks,
            };
        }

        setButtonLoading(submitButton, true, 'Saving...');

        try {
            const response = await postData(routes[action], payload);
            table.loadData(table.getCurrentPage());
            showToast(response.message);
            hideModal();
        } catch (error) {
            showToast(error.message || 'Unable to update stock.', 'error');
        } finally {
            setButtonLoading(submitButton, false);
        }
    });

    bulkForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const action = bulkActionSelect?.value;
        const remarks = bulkRemarksInput?.value ?? '';
        const items = collectBulkItems(action);

        if (items.length === 0) {
            showToast('Enter a quantity for at least one variant.', 'error');
            return;
        }

        setButtonLoading(bulkSubmitButton, true, 'Saving...');

        try {
            const response = await postData(window.tableConfig.bulkUrl, {
                product_id: window.tableConfig.productId,
                action,
                remarks,
                items,
            });

            const successCount = applyBulkResults(response.results ?? []);
            const total = response.results?.length ?? 0;

            if (successCount === total) {
                showToast('Bulk update saved.');
                hideBulkModal();
            } else {
                bulkStatus.textContent = `${successCount} of ${total} saved. Fix the highlighted rows.`;
                bulkStatus.classList.remove('hidden');
                showToast(`${successCount} of ${total} saved. Fix the highlighted rows.`, 'warning');
            }
        } catch (error) {
            showToast(error.message || 'Unable to save bulk update.', 'error');
        } finally {
            setButtonLoading(bulkSubmitButton, false);
        }
    });
});
</script>
@endpush
