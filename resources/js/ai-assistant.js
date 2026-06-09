import { escapeHtml } from './data-table';
import { renderColorImageIconButton } from './color-image';

const SAMPLE_MESSAGE = 'Boss pa order po 10 pcs reversible black white regular need sa Friday';

let currentDraft = null;
let previewItems = [];
let productCellsCache = {};

document.addEventListener('DOMContentLoaded', () => {
    const config = window.aiAssistantConfig;
    if (!config) {
        return;
    }

    bindActions(config);
    loadRecentDrafts(config);
});

function bindActions(config) {
    document.getElementById('new-draft-btn')?.addEventListener('click', resetWorkspace);
    document.getElementById('paste-sample-btn')?.addEventListener('click', () => {
        const textarea = document.getElementById('raw-message');
        if (textarea) {
            textarea.value = SAMPLE_MESSAGE;
        }
    });
    document.getElementById('clear-message-btn')?.addEventListener('click', () => {
        const textarea = document.getElementById('raw-message');
        if (textarea) {
            textarea.value = '';
        }
    });
    document.getElementById('analyze-btn')?.addEventListener('click', () => analyzeMessage(config));
    document.getElementById('save-draft-btn')?.addEventListener('click', () => saveDraft(config));
    document.getElementById('convert-draft-btn')?.addEventListener('click', () => convertDraft(config));
    document.getElementById('reject-draft-btn')?.addEventListener('click', () => rejectDraft(config));
    document.getElementById('provider-select')?.addEventListener('change', () => switchProvider(config));
}

async function switchProvider(config) {
    const select = document.getElementById('provider-select');
    const provider = select?.value;

    if (!provider || !config.urls.setProvider) {
        return;
    }

    try {
        await postData(config.urls.setProvider, { provider });
        showToast('Default AI provider updated.');
        window.location.reload();
    } catch (error) {
        showToast(error.message || 'Unable to switch provider.', 'error');
    }
}

async function analyzeMessage(config) {
    const message = document.getElementById('raw-message')?.value?.trim() ?? '';
    const button = document.getElementById('analyze-btn');
    const loading = document.getElementById('analyze-loading');

    if (!message) {
        showToast('Paste a customer message first.', 'error');
        return;
    }

    if (!config.connected) {
        showToast('No AI provider is connected.', 'error');
        return;
    }

    setButtonLoading(button, true, 'Analyzing...');
    loading?.classList.remove('hidden');

    try {
        const response = await postData(config.urls.analyze, { raw_message: message });
        currentDraft = response.draft;
        hydratePreview(currentDraft, config);
        showToast(response.message || 'Conversation analyzed.');
        loadRecentDrafts(config);
    } catch (error) {
        showToast(error.message || 'Unable to analyze conversation.', 'error');
    } finally {
        setButtonLoading(button, false);
        loading?.classList.add('hidden');
    }
}

function hydratePreview(draft, config) {
    document.getElementById('preview-card')?.classList.remove('hidden');
    document.getElementById('preview-confidence').textContent = draft.confidence_score
        ? `Confidence: ${Math.round(draft.confidence_score * 100)}%`
        : '';

    document.getElementById('preview-customer-name').value = draft.customer_name ?? '';
    document.getElementById('preview-customer-contact').value = draft.customer_contact ?? '';
    document.getElementById('preview-customer-source').value = draft.customer_source ?? 'facebook';
    document.getElementById('preview-customer-notes').value = draft.customer_notes ?? '';

    previewItems = (draft.matched_json?.items ?? []).map((item) => ({
        ...item,
        quantity: item.parsed?.quantity ?? 1,
    }));

    renderPreviewItems(config);
}

async function renderPreviewItems(config) {
    const tbody = document.getElementById('preview-items-body');
    if (!tbody) {
        return;
    }

    tbody.innerHTML = previewItems.map((item, index) => renderPreviewRow(item, index, config)).join('');

    for (const select of tbody.querySelectorAll('.preview-product-select')) {
        select.addEventListener('change', async (event) => {
            const rowIndex = Number(event.target.dataset.index);
            await handleProductChange(rowIndex, config);
        });
    }

    for (const select of tbody.querySelectorAll('.preview-color-select, .preview-size-select')) {
        select.addEventListener('change', (event) => {
            const rowIndex = Number(event.target.dataset.index);
            handleCellSelection(rowIndex, config);
        });
    }

    for (const input of tbody.querySelectorAll('.preview-qty-input')) {
        input.addEventListener('input', (event) => {
            const rowIndex = Number(event.target.dataset.index);
            previewItems[rowIndex].quantity = Number(event.target.value || 0);
            updateRowStatus(rowIndex);
        });
    }

    tbody.querySelectorAll('.remove-preview-item').forEach((button) => {
        button.addEventListener('click', () => {
            previewItems.splice(Number(button.dataset.index), 1);
            renderPreviewItems(config);
        });
    });

    for (let index = 0; index < previewItems.length; index += 1) {
        if (previewItems[index].product_id) {
            await ensureProductCells(previewItems[index].product_id, config);
            populateColorSizeSelects(index, config);
            updateRowStatus(index);
        }
    }
}

function renderPreviewRow(item, index, config) {
    const statusBadge = itemStatusBadge(item);
    const productOptions = config.products.map((product) =>
        `<option value="${product.id}" ${Number(item.product_id) === Number(product.id) ? 'selected' : ''}>${escapeHtml(product.name)}</option>`
    ).join('');

    return `
        <tr data-preview-index="${index}">
            <td>
                <select class="preview-product-select ui-input min-w-40" data-index="${index}">
                    <option value="">Select product...</option>
                    ${productOptions}
                </select>
            </td>
            <td>
                <div class="flex items-center gap-2">
                    <select class="preview-color-select ui-input min-w-32" data-index="${index}"></select>
                    ${renderColorImageIconButton({
                        imageUrl: item.image_url ?? '',
                        colorName: item.color_name ?? '',
                        itemCode: item.item_code ?? '',
                        disabled: !item.cell_id,
                    })}
                </div>
            </td>
            <td><select class="preview-size-select ui-input min-w-28" data-index="${index}"></select></td>
            <td><input type="number" min="1" class="preview-qty-input ui-input w-20" data-index="${index}" value="${item.quantity ?? 1}" /></td>
            <td class="available-stock-cell">${escapeHtml(String(item.available_stock ?? '—'))}</td>
            <td class="status-cell">${statusBadge}</td>
            <td><button type="button" class="ui-row-action ui-row-action-danger remove-preview-item" data-index="${index}">Remove</button></td>
        </tr>
    `;
}

function itemStatusBadge(item) {
    if (!item.cell_id) {
        return badge('Needs Review', 'bg-amber-100 text-amber-800');
    }

    if (item.shortage || Number(item.quantity) > Number(item.available_stock ?? 0)) {
        return badge('Shortage', 'bg-red-100 text-red-800');
    }

    return badge('Matched', 'bg-green-100 text-green-800');
}

function badge(label, classes) {
    return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes}">${escapeHtml(label)}</span>`;
}

async function ensureProductCells(productId, config) {
    if (productCellsCache[productId]) {
        return productCellsCache[productId];
    }

    const response = await fetch(`${config.urls.productCells}?product_id=${encodeURIComponent(productId)}`);
    const data = await response.json();
    productCellsCache[productId] = data.cells ?? [];

    return productCellsCache[productId];
}

async function handleProductChange(index, config) {
    const row = document.querySelector(`tr[data-preview-index="${index}"]`);
    const productId = Number(row?.querySelector('.preview-product-select')?.value ?? 0);
    previewItems[index].product_id = productId || null;
    previewItems[index].cell_id = null;
    previewItems[index].available_stock = 0;
    previewItems[index].matched = false;
    previewItems[index].status = 'needs_review';

    if (!productId) {
        renderPreviewItems(config);
        return;
    }

    await ensureProductCells(productId, config);
    populateColorSizeSelects(index, config);
    updateRowStatus(index);
}

function populateColorSizeSelects(index, config) {
    const row = document.querySelector(`tr[data-preview-index="${index}"]`);
    const productId = Number(previewItems[index].product_id);
    const cells = productCellsCache[productId] ?? [];
    const colorSelect = row?.querySelector('.preview-color-select');
    const sizeSelect = row?.querySelector('.preview-size-select');

    const colors = [...new Set(cells.map((cell) => cell.color_name))];
    colorSelect.innerHTML = '<option value="">Select color...</option>' + colors.map((name) =>
        `<option value="${escapeHtml(name)}" ${previewItems[index].color_name === name ? 'selected' : ''}>${escapeHtml(name)}</option>`
    ).join('');

    const selectedColor = colorSelect.value || previewItems[index].color_name;
    const sizes = cells.filter((cell) => cell.color_name === selectedColor);
    sizeSelect.innerHTML = '<option value="">Select size...</option>' + sizes.map((cell) =>
        `<option value="${cell.cell_id}" ${Number(previewItems[index].cell_id) === Number(cell.cell_id) ? 'selected' : ''}>${escapeHtml(cell.size_name)} (${cell.available_stock} avail.)</option>`
    ).join('');
}

function handleCellSelection(index, config) {
    const row = document.querySelector(`tr[data-preview-index="${index}"]`);
    const productId = Number(previewItems[index].product_id);
    const cells = productCellsCache[productId] ?? [];
    const colorName = row?.querySelector('.preview-color-select')?.value ?? '';
    const cellId = Number(row?.querySelector('.preview-size-select')?.value ?? 0);
    const cell = cells.find((entry) => Number(entry.cell_id) === cellId);

    previewItems[index].color_name = colorName;
    previewItems[index].cell_id = cell?.cell_id ?? null;
    previewItems[index].available_stock = cell?.available_stock ?? 0;
    previewItems[index].item_code = cell?.item_code ?? null;
    previewItems[index].image_url = cell?.image_url ?? '';
    previewItems[index].product_name = cell?.product_name ?? previewItems[index].product_name;
    previewItems[index].size_name = cell?.size_name ?? previewItems[index].size_name;
    previewItems[index].matched = Boolean(cell);
    previewItems[index].status = cell ? 'matched' : 'needs_review';

    updateRowStatus(index);
}

function updateRowStatus(index) {
    const row = document.querySelector(`tr[data-preview-index="${index}"]`);
    const item = previewItems[index];
    item.shortage = item.cell_id && Number(item.quantity) > Number(item.available_stock ?? 0);
    row?.querySelector('.available-stock-cell')?.replaceChildren(document.createTextNode(String(item.available_stock ?? '—')));
    const statusCell = row?.querySelector('.status-cell');
    if (statusCell) {
        statusCell.innerHTML = itemStatusBadge(item);
    }

    const imageButton = row?.querySelector('.color-image-view-trigger');
    if (imageButton) {
        const replacement = document.createElement('span');
        replacement.innerHTML = renderColorImageIconButton({
            imageUrl: item.image_url ?? '',
            colorName: item.color_name ?? '',
            itemCode: item.item_code ?? '',
            disabled: !item.cell_id,
        });
        imageButton.replaceWith(replacement.firstElementChild);
    }
}

function collectReviewPayload() {
    return {
        customer_name: document.getElementById('preview-customer-name')?.value?.trim() ?? '',
        customer_contact: document.getElementById('preview-customer-contact')?.value?.trim() ?? '',
        customer_source: document.getElementById('preview-customer-source')?.value ?? 'facebook',
        customer_notes: document.getElementById('preview-customer-notes')?.value?.trim() ?? '',
        items: previewItems
            .filter((item) => item.cell_id && Number(item.quantity) > 0)
            .map((item) => ({
                product_color_size_id: item.cell_id,
                quantity: Number(item.quantity),
            })),
        matched_json: { items: previewItems },
    };
}

async function saveDraft(config) {
    if (!currentDraft?.id) {
        showToast('Analyze a conversation first.', 'error');
        return;
    }

    const payload = collectReviewPayload();

    try {
        const response = await postData(`${config.urls.draftUpdate}/${currentDraft.id}`, payload, 'PUT');
        currentDraft = response.draft;
        showToast(response.message || 'Draft saved.');
        loadRecentDrafts(config);
    } catch (error) {
        showToast(error.message || 'Unable to save draft.', 'error');
    }
}

async function convertDraft(config) {
    if (!currentDraft?.id) {
        showToast('Analyze a conversation first.', 'error');
        return;
    }

    const payload = collectReviewPayload();

    if (!payload.customer_name) {
        showToast('Customer name is required.', 'error');
        return;
    }

    if (previewItems.some((item) => !item.cell_id)) {
        showToast('All items must be matched before creating an order.', 'error');
        return;
    }

    if (payload.items.length === 0) {
        showToast('Add at least one valid line item.', 'error');
        return;
    }

    const button = document.getElementById('convert-draft-btn');
    setButtonLoading(button, true, 'Creating...');

    try {
        const response = await postData(`${config.urls.draftConvert}/${currentDraft.id}/convert`, payload);
        showToast(response.message || 'Customer order created.');
        window.location.href = response.redirect_url;
    } catch (error) {
        showToast(error.message || 'Unable to create customer order.', 'error');
    } finally {
        setButtonLoading(button, false);
    }
}

async function rejectDraft(config) {
    if (!currentDraft?.id) {
        showToast('No draft selected.', 'error');
        return;
    }

    if (!confirm('Reject this draft?')) {
        return;
    }

    try {
        await postData(`${config.urls.draftReject}/${currentDraft.id}/reject`);
        showToast('Draft rejected.');
        resetWorkspace();
        loadRecentDrafts(config);
    } catch (error) {
        showToast(error.message || 'Unable to reject draft.', 'error');
    }
}

async function loadRecentDrafts(config) {
    const container = document.getElementById('recent-drafts-list');
    if (!container) {
        return;
    }

    try {
        const response = await fetch(`${config.urls.drafts}?per_page=10`);
        const data = await response.json();
        const rows = data.data ?? [];

        if (!rows.length) {
            container.innerHTML = '<p class="px-2 py-3 text-[13px] text-gray-500">No drafts yet.</p>';
            return;
        }

        container.innerHTML = rows.map((draft) => `
            <button type="button" class="draft-open w-full rounded-md px-2 py-2 text-left hover:bg-gray-50" data-id="${draft.id}">
                <p class="truncate text-[13px] font-medium text-gray-900">${escapeHtml(draft.message_preview)}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">${escapeHtml(draft.created_at)} · ${escapeHtml(draft.status_label)}</p>
            </button>
        `).join('');

        container.querySelectorAll('.draft-open').forEach((button) => {
            button.addEventListener('click', () => openDraft(Number(button.dataset.id), config));
        });
    } catch (_) {
        container.innerHTML = '<p class="px-2 py-3 text-[13px] text-red-600">Unable to load drafts.</p>';
    }
}

async function openDraft(id, config) {
    try {
        const response = await fetch(`${config.urls.draftShow}/${id}`);
        const data = await response.json();
        currentDraft = data.draft;
        document.getElementById('raw-message').value = currentDraft.raw_message ?? '';
        hydratePreview(currentDraft, config);
    } catch (error) {
        showToast('Unable to open draft.', 'error');
    }
}

function resetWorkspace() {
    currentDraft = null;
    previewItems = [];
    document.getElementById('raw-message').value = '';
    document.getElementById('preview-card')?.classList.add('hidden');
    document.getElementById('preview-items-body').innerHTML = '';
}
