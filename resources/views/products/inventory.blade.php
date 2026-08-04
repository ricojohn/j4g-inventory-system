@extends('layouts.app')

@section('page-title', 'Manage Inventory')

@section('content')
    <x-ui.page-header
        eyebrow="Ledger-based stock"
        :title="$product->name"
        :subtitle="$product->code.' · On hand minus active reservations'"
    >
        <x-slot:actions>
            @if (! $readOnly)
                @canany(['stock in', 'stock out', 'reserve stock', 'release stock', 'damage stock', 'adjust stock'])
                    <x-ui.button type="button" variant="secondary" id="open-bulk-modal">Bulk Update</x-ui.button>
                @endcanany
            @endif
            <x-ui.button variant="secondary" :href="route('products.index')">Back to Products</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($readOnly)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
            This product is inactive — reactivate it from the edit page to manage stock.
        </div>
    @endif

    <div id="inventory-summary" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Total Colors" value="—" data-summary="total-colors" />
        <x-ui.stat-card label="Total Size Cells / SKUs" value="—" data-summary="total-skus" />
        <x-ui.stat-card label="Total Current Stock" value="—" data-summary="total-stock" />
        <x-ui.stat-card label="Total Reserved Quantity" value="—" data-summary="total-reserved" />
        <x-ui.stat-card label="Total Available Stock" value="—" data-summary="total-available" />
        <x-ui.stat-card label="Low Stock Count" value="—" accent="warning" data-summary="low-stock-count" />
        <x-ui.stat-card label="Out of Stock Count" value="—" accent="danger" data-summary="out-of-stock-count" />
    </div>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.input type="search" id="inventory-search" placeholder="Search colors..." class="w-auto! min-w-48" />
                <x-ui.select id="inventory-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <div id="inventory-grid-wrap" class="inventory-grid-scroll">
            <p class="p-4 text-[13px] text-gray-500">Loading inventory grid...</p>
        </div>

        <div id="inventory-pagination" class="hidden border-t border-gray-200 px-4 py-3"></div>
    </x-ui.page-card>

    <div id="bulk-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
            <div class="ui-modal-header shrink-0">
                <h2 class="text-[13px] font-semibold text-gray-900">Bulk Update</h2>
                <p class="mt-0.5 text-[13px] text-gray-500">{{ $product->name }} — enter a quantity for each cell you want to include.</p>
            </div>
            <form id="bulk-form" class="flex min-h-0 flex-1 flex-col">
                <div class="flex min-h-0 flex-1 flex-col gap-3 p-4">
                    <div class="grid shrink-0 gap-3 sm:grid-cols-2">
                        <div>
                            <x-ui.label for="bulk-action">Action</x-ui.label>
                            <x-ui.select id="bulk-action"></x-ui.select>
                        </div>
                        <div>
                            <x-ui.label for="bulk-remarks">Remarks</x-ui.label>
                            <x-ui.input id="bulk-remarks" type="text" />
                        </div>
                    </div>
                    <div id="bulk-rows-scroll" class="flex min-h-0 flex-1 overflow-y-auto rounded border border-gray-200">
                        <table class="ui-table">
                            <thead class="sticky top-0 z-10 bg-gray-50">
                                <tr>
                                    <th>Color</th>
                                    <th>Size</th>
                                    <th>Current</th>
                                    <th id="bulk-qty-header">Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="bulk-rows"></tbody>
                        </table>
                    </div>
                    <p id="bulk-status" class="hidden shrink-0 whitespace-pre-line text-[12px] text-amber-700"></p>
                </div>
                <div class="ui-modal-footer shrink-0">
                    <x-ui.button type="button" variant="secondary" data-close="bulk-modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" id="bulk-submit">Apply</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <div id="cell-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel max-w-lg overflow-hidden">
            <div class="ui-modal-header">
                <h2 id="cell-modal-title" class="text-[13px] font-semibold text-gray-900">Update Stock</h2>
                <p id="cell-modal-subtitle" class="mt-0.5 text-[13px] text-gray-500"></p>
            </div>
            <form id="cell-form">
                <div class="ui-modal-body space-y-3">
                    <input type="hidden" id="cell-id">
                    <div>
                        <x-ui.label for="cell-action">Action</x-ui.label>
                        <x-ui.select id="cell-action"></x-ui.select>
                    </div>
                    <div id="cell-qty-wrap">
                        <x-ui.label for="cell-quantity">Quantity</x-ui.label>
                        <x-ui.input id="cell-quantity" type="number" min="1" />
                    </div>
                    <div id="cell-new-qty-wrap" class="hidden">
                        <x-ui.label for="cell-new-quantity">New Quantity</x-ui.label>
                        <x-ui.input id="cell-new-quantity" type="number" min="0" />
                        <x-ui.label for="cell-reorder-level" class="mt-2">Reorder Level</x-ui.label>
                        <x-ui.input id="cell-reorder-level" type="number" min="0" />
                    </div>
                    <div>
                        <x-ui.label for="cell-remarks">Remarks</x-ui.label>
                        <x-ui.textarea id="cell-remarks" rows="2"></x-ui.textarea>
                    </div>
                    <div class="border-t border-gray-200 pt-3">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h3 class="text-[13px] font-semibold text-gray-900">Recent Stock History</h3>
                            @can('view stock history')
                                <a id="cell-history-full-link" href="#" class="text-[12px] font-medium text-slate-700 hover:text-slate-900">View Full History</a>
                            @endcan
                        </div>
                        <div id="cell-history" class="text-[12px] text-gray-500">Loading history...</div>
                    </div>
                </div>
                <div class="ui-modal-footer">
                    <x-ui.button type="button" variant="secondary" data-close="cell-modal">Cancel</x-ui.button>
                    <x-ui.button type="submit">Submit</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <div id="color-image-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
        <div class="ui-modal-panel max-w-md overflow-hidden">
            <div class="ui-modal-header">
                <h2 class="text-[13px] font-semibold text-gray-900">Color Image</h2>
                <p id="color-image-subtitle" class="mt-0.5 text-[13px] text-gray-500"></p>
            </div>
            <div class="ui-modal-body space-y-3">
                <input type="hidden" id="color-image-id">
                <div class="flex items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
                    <img id="color-image-preview" src="" alt="Color preview" class="hidden max-h-64 w-auto rounded object-contain">
                    <p id="color-image-empty" class="py-10 text-[13px] text-gray-500">No image uploaded yet.</p>
                </div>
                @if (! $readOnly)
                    @can('edit products')
                        <div>
                            <x-ui.label for="color-image-file">Choose Image</x-ui.label>
                            <input id="color-image-file" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-[13px] text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-[13px] file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                            <p class="mt-1 text-[11px] text-gray-500">JPG, PNG, or WEBP up to 2MB.</p>
                        </div>
                    @endcan
                @endif
            </div>
            <div class="ui-modal-footer">
                <x-ui.button type="button" variant="secondary" data-close="color-image-modal">Close</x-ui.button>
                @if (! $readOnly)
                    @can('edit products')
                        <x-ui.button type="button" variant="danger" id="color-image-remove" class="hidden">Remove</x-ui.button>
                        <x-ui.button type="button" id="color-image-upload">Upload</x-ui.button>
                    @endcan
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@php
    $inventoryConfig = [
        'productId' => $product->id,
        'dataUrl' => route('products.inventory.data', $product),
        'cellHistoryUrlBase' => url('/inventory/cell'),
        'colorImageUrlBase' => url('/products/'.$product->id.'/colors'),
        'canEditProducts' => auth()->user()?->can('edit products'),
        'canViewStockHistory' => auth()->user()?->can('view stock history'),
        'stockHistoryUrl' => auth()->user()?->can('view stock history')
            ? route('reports.stock-history', ['product_id' => $product->id])
            : null,
        'readOnly' => $readOnly,
        'permissions' => [
            'stockIn' => auth()->user()?->can('stock in'),
            'stockOut' => auth()->user()?->can('stock out'),
            'reserve' => auth()->user()?->can('reserve stock'),
            'release' => auth()->user()?->can('release stock'),
            'damage' => auth()->user()?->can('damage stock'),
            'adjust' => auth()->user()?->can('adjust stock'),
        ],
        'routes' => [
            'stockIn' => route('inventory.stock-in'),
            'stockOut' => route('inventory.stock-out'),
            'reserve' => route('inventory.reserve'),
            'release' => route('inventory.release'),
            'damage' => route('inventory.damage'),
            'adjust' => route('inventory.adjust'),
            'bulk' => route('inventory.bulk'),
        ],
    ];
@endphp
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($inventoryConfig);
    let gridData = null;
    let currentPage = 1;

    const actionOptions = [];
    if (config.permissions.stockIn) actionOptions.push({ value: 'stock-in', label: 'Stock In', route: config.routes.stockIn });
    if (config.permissions.stockOut) actionOptions.push({ value: 'stock-out', label: 'Stock Out', route: config.routes.stockOut });
    if (config.permissions.reserve) actionOptions.push({ value: 'reserve', label: 'Reserve', route: config.routes.reserve });
    if (config.permissions.release) actionOptions.push({ value: 'release', label: 'Release', route: config.routes.release });
    if (config.permissions.damage) actionOptions.push({ value: 'damage', label: 'Damage', route: config.routes.damage });
    if (config.permissions.adjust) actionOptions.push({ value: 'adjust', label: 'Adjust', route: config.routes.adjust });

    const actionSelect = document.getElementById('cell-action');
    actionSelect.innerHTML = actionOptions.map(o => `<option value="${o.value}">${o.label}</option>`).join('');

    async function loadGrid(page = 1) {
        currentPage = page;
        const wrap = document.getElementById('inventory-grid-wrap');
        wrap.innerHTML = '<p class="p-4 text-[13px] text-gray-500">Loading inventory grid...</p>';

        const params = new URLSearchParams({
            page: String(currentPage),
            per_page: String(document.getElementById('inventory-per-page')?.value ?? 20),
        });

        const search = document.getElementById('inventory-search')?.value?.trim() ?? '';
        if (search) {
            params.set('search', search);
        }

        const response = await fetch(`${config.dataUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        gridData = await response.json();
        renderSummary();
        renderGrid();

        const paginationContainer = document.getElementById('inventory-pagination');
        if (paginationContainer && gridData.pagination) {
            renderPagination(paginationContainer, gridData.pagination, loadGrid);
        }
    }

    function renderSummary() {
        const summary = gridData?.summary;
        if (!summary) {
            return;
        }

        const map = {
            'total-colors': summary.total_colors,
            'total-skus': summary.total_skus,
            'total-stock': summary.total_stock,
            'total-reserved': summary.total_reserved,
            'total-available': summary.total_available,
            'low-stock-count': summary.low_stock_count,
            'out-of-stock-count': summary.out_of_stock_count,
        };

        Object.entries(map).forEach(([key, value]) => {
            const card = document.querySelector(`[data-summary="${key}"] .stat-value`);
            if (card) {
                card.textContent = value;
            }
        });
    }

    function renderGrid() {
        const wrap = document.getElementById('inventory-grid-wrap');
        const { sizes, colors } = gridData;

        if (!sizes.length) {
            wrap.innerHTML = '<p class="p-4 text-[13px] text-gray-500">Add sizes and colors on the product edit page first.</p>';
            return;
        }

        if (!colors.length) {
            wrap.innerHTML = '<p class="p-4 text-[13px] text-gray-500">No colors match your search.</p>';
            return;
        }

        let html = '<table class="ui-table inventory-grid-table min-w-full"><thead><tr><th class="inventory-sticky-corner">Color</th>';
        sizes.forEach(s => { html += `<th class="inventory-sticky-header">${escapeHtml(s.size_name)}</th>`; });
        html += '</tr></thead><tbody>';

        colors.forEach(color => {
            const thumb = color.image_url
                ? `<img src="${escapeHtml(color.image_url)}" alt="${escapeHtml(color.color_name)}" class="h-9 w-9 shrink-0 rounded object-cover ring-1 ring-gray-200">`
                : `<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-400" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 9h.008v.008H18V9zm.75 9.75H5.25A2.25 2.25 0 013 16.5V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25z" /></svg>
                    </span>`;
            const colorTrigger = `data-color-id="${color.id}" data-color-name="${escapeHtml(color.color_name)}" data-item-code="${escapeHtml(color.item_code)}" data-image-url="${escapeHtml(color.image_url ?? '')}"`;
            html += `<tr><td class="inventory-sticky-col whitespace-nowrap">
                    <button type="button" class="color-image-trigger flex items-center gap-2 text-left hover:opacity-80" ${colorTrigger} title="Manage color image">
                        ${thumb}
                        <span>
                            <span class="block font-medium">${escapeHtml(color.item_code)}</span>
                            <span class="block text-gray-500">${escapeHtml(color.color_name)}</span>
                        </span>
                    </button>
                </td>`;
            sizes.forEach(size => {
                const cell = color.cells[size.id];
                if (!cell) {
                    html += '<td>-</td>';
                    return;
                }
                const reserved = cell.reserved_quantity > 0 ? `<div class="text-[11px] text-gray-500">(${cell.reserved_quantity} reserved)</div>` : '';
                const clickable = !config.readOnly && actionOptions.length ? 'cursor-pointer hover:bg-gray-50' : '';
                html += `<td class="${clickable} p-2" data-cell-id="${cell.id}" data-cell-label="${escapeHtml(color.color_name)} / ${escapeHtml(size.size_name)}">
                    <div class="font-medium">${cell.current_stock}</div>${reserved}
                    ${renderStockBadge(cell.status, cell.status_label)}
                </td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        wrap.innerHTML = html;
    }

    document.getElementById('inventory-grid-wrap')?.addEventListener('click', (e) => {
        const colorTrigger = e.target.closest('.color-image-trigger');
        if (colorTrigger) {
            openColorImageModal(colorTrigger.dataset);
            return;
        }

        const td = e.target.closest('[data-cell-id]');
        if (!td || config.readOnly) return;
        openCellModal(td.dataset.cellId, td.dataset.cellLabel);
    });

    const cellModal = document.getElementById('cell-modal');
    const bulkModal = document.getElementById('bulk-modal');
    const colorImageModal = document.getElementById('color-image-modal');

    const closeOverlay = (overlay) => {
        overlay.classList.add('hidden');
    };

    [cellModal, bulkModal, colorImageModal].forEach((overlay) => {
        if (! overlay) {
            return;
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeOverlay(overlay);
            }
        });

        overlay.querySelectorAll('[data-close]').forEach((btn) => {
            btn.addEventListener('click', () => closeOverlay(overlay));
        });
    });

    function openCellModal(cellId, label) {
        document.getElementById('cell-id').value = cellId;
        document.getElementById('cell-modal-subtitle').textContent = label;

        const fullLink = document.getElementById('cell-history-full-link');
        if (fullLink && config.stockHistoryUrl) {
            fullLink.href = config.stockHistoryUrl;
        }

        loadCellHistory(cellId);
        cellModal.classList.remove('hidden');
    }

    let currentColorId = null;

    function setColorImagePreview(url) {
        const preview = document.getElementById('color-image-preview');
        const empty = document.getElementById('color-image-empty');
        const removeBtn = document.getElementById('color-image-remove');

        if (url) {
            preview.src = url;
            preview.classList.remove('hidden');
            empty.classList.add('hidden');
            removeBtn?.classList.remove('hidden');
        } else {
            preview.src = '';
            preview.classList.add('hidden');
            empty.classList.remove('hidden');
            removeBtn?.classList.add('hidden');
        }
    }

    function openColorImageModal(dataset) {
        currentColorId = dataset.colorId;
        document.getElementById('color-image-id').value = dataset.colorId;
        document.getElementById('color-image-subtitle').textContent = `${dataset.itemCode} · ${dataset.colorName}`;

        const fileInput = document.getElementById('color-image-file');
        if (fileInput) {
            fileInput.value = '';
        }

        setColorImagePreview(dataset.imageUrl || '');
        colorImageModal.classList.remove('hidden');
    }

    document.getElementById('color-image-upload')?.addEventListener('click', async () => {
        const fileInput = document.getElementById('color-image-file');
        const file = fileInput?.files?.[0];

        if (!file) {
            showToast('Choose an image first.', 'error');
            return;
        }

        const button = document.getElementById('color-image-upload');
        setButtonLoading(button, true, 'Uploading...');

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch(`${config.colorImageUrlBase}/${currentColorId}/image`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok) {
                const message = payload.message
                    || (payload.errors ? Object.values(payload.errors).flat()[0] : null)
                    || 'Upload failed.';
                throw new Error(message);
            }

            setColorImagePreview(payload.image_url || '');
            if (fileInput) {
                fileInput.value = '';
            }
            showToast(payload.message || 'Image uploaded.');
            await loadGrid(currentPage);
        } catch (error) {
            showToast(error.message || 'Unable to upload image.', 'error');
        } finally {
            setButtonLoading(button, false);
        }
    });

    document.getElementById('color-image-remove')?.addEventListener('click', async () => {
        if (!currentColorId || !confirm('Remove this color image?')) return;

        const button = document.getElementById('color-image-remove');
        setButtonLoading(button, true, 'Removing...');

        try {
            const response = await postData(`${config.colorImageUrlBase}/${currentColorId}/image`, {}, 'DELETE');
            setColorImagePreview('');
            showToast(response.message || 'Image removed.');
            await loadGrid(currentPage);
        } catch (error) {
            showToast(error.message || 'Unable to remove image.', 'error');
        } finally {
            setButtonLoading(button, false);
        }
    });

    async function loadCellHistory(cellId) {
        const container = document.getElementById('cell-history');
        container.innerHTML = '<p class="text-gray-500">Loading history...</p>';

        try {
            const response = await fetch(`${config.cellHistoryUrlBase}/${cellId}/history`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Unable to load history.');
            }

            renderCellHistory(payload.movements || []);
        } catch (error) {
            container.innerHTML = `<p class="text-red-600">${escapeHtml(error.message || 'Unable to load history.')}</p>`;
        }
    }

    function renderCellHistory(movements) {
        const container = document.getElementById('cell-history');

        if (!movements.length) {
            container.innerHTML = '<p class="text-gray-500">No stock movements recorded for this cell yet.</p>';
            return;
        }

        container.innerHTML = `
            <div class="max-h-48 space-y-2 overflow-y-auto">
                ${movements.map((movement) => `
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-gray-900">${escapeHtml(movement.movement_type)}</span>
                            <span class="text-gray-500">${escapeHtml(movement.created_at)}</span>
                        </div>
                        <div class="mt-1 text-gray-700">Qty: ${escapeHtml(movement.quantity)} · ${escapeHtml(movement.before_stock)} → ${escapeHtml(movement.after_stock)}</div>
                        <div class="mt-1 text-gray-500">By ${escapeHtml(movement.user_name)}</div>
                        ${movement.remarks ? `<div class="mt-1 text-gray-600">${escapeHtml(movement.remarks)}</div>` : ''}
                    </div>
                `).join('')}
            </div>
        `;
    }

    actionSelect?.addEventListener('change', () => {
        const isAdjust = actionSelect.value === 'adjust';
        document.getElementById('cell-qty-wrap').classList.toggle('hidden', isAdjust);
        document.getElementById('cell-new-qty-wrap').classList.toggle('hidden', !isAdjust);
    });

    document.getElementById('cell-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = actionOptions.find(o => o.value === actionSelect.value);
        if (!action) return;

        const payload = {
            cell_id: Number(document.getElementById('cell-id').value),
            remarks: document.getElementById('cell-remarks').value || null,
        };

        if (action.value === 'adjust') {
            payload.new_quantity = Number(document.getElementById('cell-new-quantity').value);
            payload.reorder_level = Number(document.getElementById('cell-reorder-level').value);
            payload.remarks = document.getElementById('cell-remarks').value;
        } else {
            payload.quantity = Number(document.getElementById('cell-quantity').value);
        }

        try {
            await postData(action.route, payload);
            showToast('Stock updated.');
            closeOverlay(cellModal);
            await loadGrid(currentPage);
            window.dispatchEvent(new CustomEvent('inventory:updated'));
        } catch (error) {
            showToast(error.message || 'Unable to update stock.', 'error');
        }
    });

    window.addEventListener('inventory:updated', () => loadGrid(currentPage));

    document.getElementById('inventory-search')?.addEventListener('input', debounce(() => loadGrid(1), 300));
    document.getElementById('inventory-per-page')?.addEventListener('change', () => loadGrid(1));

    const bulkActionSelect = document.getElementById('bulk-action');
    const bulkRowsBody = document.getElementById('bulk-rows');
    const bulkQtyHeader = document.getElementById('bulk-qty-header');
    const bulkStatus = document.getElementById('bulk-status');
    const bulkSubmit = document.getElementById('bulk-submit');

    if (bulkActionSelect) {
        bulkActionSelect.innerHTML = actionOptions
            .map(o => `<option value="${o.value}">${o.label}</option>`)
            .join('');
    }

    document.getElementById('open-bulk-modal')?.addEventListener('click', () => {
        if (!gridData || !actionOptions.length) return;
        renderBulkRows();
        bulkStatus.classList.add('hidden');
        bulkStatus.textContent = '';
        document.getElementById('bulk-rows-scroll')?.scrollTo(0, 0);
        bulkModal.classList.remove('hidden');
    });

    bulkActionSelect?.addEventListener('change', () => {
        bulkQtyHeader.textContent = bulkActionSelect.value === 'adjust' ? 'New Quantity' : 'Quantity';
    });

    function renderBulkRows() {
        if (!gridData) return;
        const rows = [];
        gridData.colors.forEach(color => {
            gridData.sizes.forEach(size => {
                const cell = color.cells[size.id];
                if (!cell) return;
                rows.push(`
                    <tr>
                        <td>${escapeHtml(color.color_name)}</td>
                        <td>${escapeHtml(size.size_name)}</td>
                        <td>${cell.current_stock}</td>
                        <td><input type="number" min="0" class="bulk-qty h-9 w-24 rounded-lg border border-gray-300 px-3 text-[13px] text-gray-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200" data-cell-id="${cell.id}"></td>
                    </tr>
                `);
            });
        });
        bulkRowsBody.innerHTML = rows.join('');
        bulkQtyHeader.textContent = bulkActionSelect.value === 'adjust' ? 'New Quantity' : 'Quantity';
    }

    document.getElementById('bulk-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = bulkActionSelect.value;
        const isAdjust = action === 'adjust';
        const remarks = document.getElementById('bulk-remarks').value;

        const items = [];
        bulkRowsBody.querySelectorAll('.bulk-qty').forEach(input => {
            const raw = input.value.trim();
            if (raw === '') return;
            const qty = Number(raw);
            if (Number.isNaN(qty)) return;
            const cellId = Number(input.dataset.cellId);
            items.push(isAdjust
                ? { cell_id: cellId, new_quantity: qty }
                : { cell_id: cellId, quantity: qty });
        });

        if (!items.length) {
            bulkStatus.textContent = 'Enter a quantity for at least one row.';
            bulkStatus.classList.remove('hidden');
            return;
        }

        bulkSubmit.disabled = true;
        bulkStatus.classList.add('hidden');

        try {
            const response = await postData(config.routes.bulk, {
                product_id: config.productId,
                action,
                remarks: remarks || null,
                items,
            });

            const failed = (response.results || []).filter(r => !r.success);
            if (failed.length) {
                const messages = failed.map(r => `Cell #${r.cell_id}: ${r.message}`).join('\n');
                bulkStatus.textContent = `${response.message}\n${messages}`;
                bulkStatus.classList.remove('hidden');
            } else {
                showToast(response.message || 'Bulk update applied.');
                closeOverlay(bulkModal);
            }

            await loadGrid(currentPage);
            window.dispatchEvent(new CustomEvent('inventory:updated'));
        } catch (error) {
            bulkStatus.textContent = error.message || 'Bulk update failed.';
            bulkStatus.classList.remove('hidden');
        } finally {
            bulkSubmit.disabled = false;
        }
    });

    loadGrid();
});
</script>
@endpush
