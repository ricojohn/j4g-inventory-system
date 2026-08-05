import { escapeHtml, debounce } from './data-table';

let boardConfig = null;
let pendingAction = null;
let draggedOrderId = null;
let draggedFromStatus = null;

document.addEventListener('DOMContentLoaded', () => {
    boardConfig = window.ordersBoardConfig;
    if (!boardConfig) {
        return;
    }

    initActionModal();
    bindFilters();
    loadBoard();
});

function bindFilters() {
    document.getElementById('board-search')?.addEventListener('input', debounce(() => loadBoard(), 300));
    document.getElementById('board-source-filter')?.addEventListener('change', () => loadBoard());
}

function initActionModal() {
    const modal = document.getElementById('board-action-modal');
    if (!modal || modal.dataset.initialized === 'true') {
        return;
    }

    modal.dataset.initialized = 'true';

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeActionModal();
        }
    });

    modal.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => closeActionModal());
    });

    document.getElementById('board-action-confirm')?.addEventListener('click', () => confirmPendingAction());
}

async function loadBoard() {
    const params = new URLSearchParams({
        search: document.getElementById('board-search')?.value ?? '',
        source: document.getElementById('board-source-filter')?.value ?? '',
    });

    try {
        const response = await fetch(`${boardConfig.dataUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to load board.');
        }

        renderAttention(data.attention ?? []);
        renderPulse(data.columns ?? []);
        renderColumns(data.columns ?? []);
    } catch (error) {
        document.getElementById('board-attention').innerHTML = `<p class="px-2 py-3 text-[13px] text-red-600">${escapeHtml(error.message || 'Unable to load board.')}</p>`;
        document.getElementById('board-columns').innerHTML = `<p class="text-[13px] text-red-600">${escapeHtml(error.message || 'Unable to load board.')}</p>`;
    }
}

function renderAttention(items) {
    const container = document.getElementById('board-attention');
    if (!container) {
        return;
    }

    if (!items.length) {
        container.innerHTML = '<p class="px-2 py-3 text-[13px] text-gray-500">No shortage or draft PO blockers right now.</p>';
        return;
    }

    container.innerHTML = items.map((order) => `
        <a href="${escapeHtml(order.show_url)}" class="flex items-start justify-between gap-3 rounded-md px-2 py-2 hover:bg-gray-50">
            <div class="min-w-0">
                <p class="truncate text-[13px] font-medium text-gray-900">${escapeHtml(order.order_number)} · ${escapeHtml(order.customer_name)}</p>
                <p class="mt-0.5 text-[12px] text-gray-500">${escapeHtml(attentionReason(order))}</p>
            </div>
            <div class="flex shrink-0 flex-wrap justify-end gap-1">
                ${order.has_shortage ? badge('Shortage', 'bg-amber-100 text-amber-800') : ''}
                ${order.has_draft_po ? badge('Draft PO', 'bg-gray-100 text-gray-700') : ''}
            </div>
        </a>
    `).join('');
}

function attentionReason(order) {
    const parts = [];
    if (order.has_shortage) {
        parts.push(`${order.shortage_qty} pcs short`);
    }
    if (order.has_draft_po) {
        parts.push(`Draft PO ${order.po_number}`);
    }
    return parts.join(' · ') || order.status_label;
}

function renderPulse(columns) {
    const container = document.getElementById('board-pulse');
    if (!container) {
        return;
    }

    container.innerHTML = columns.map((column) => `
        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">${escapeHtml(column.label)}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">${escapeHtml(String(column.count))}</p>
        </div>
    `).join('');
}

function renderColumns(columns) {
    const container = document.getElementById('board-columns');
    if (!container) {
        return;
    }

    container.innerHTML = columns.map((column) => `
        <section
            class="board-column flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-gray-50"
            data-status="${escapeHtml(column.status)}"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2">
                <h3 class="text-[13px] font-semibold text-gray-900">${escapeHtml(column.label)}</h3>
                <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-gray-700">${escapeHtml(String(column.count))}</span>
            </div>
            <div class="board-column-body min-h-40 flex-1 space-y-2 overflow-y-auto p-2" data-status="${escapeHtml(column.status)}">
                ${(column.orders ?? []).map((order) => renderCard(order)).join('') || '<p class="px-1 py-4 text-center text-[12px] text-gray-400">No orders</p>'}
            </div>
        </section>
    `).join('');

    bindDragAndDrop();
}

function renderCard(order) {
    const draggable = (order.allowed_targets ?? []).length > 0;

    return `
        <article
            class="board-card rounded-md border border-gray-200 bg-white p-3 shadow-sm ${draggable ? 'cursor-grab active:cursor-grabbing' : 'opacity-95'}"
            draggable="${draggable ? 'true' : 'false'}"
            data-order-id="${order.id}"
            data-status="${escapeHtml(order.status)}"
            data-allowed-targets="${escapeHtml((order.allowed_targets ?? []).join(','))}"
            data-can-fulfill="${order.can_fulfill ? '1' : '0'}"
            data-can-cancel="${order.can_cancel ? '1' : '0'}"
            data-order-number="${escapeHtml(order.order_number)}"
        >
            <div class="flex items-start justify-between gap-2">
                <a href="${escapeHtml(order.show_url)}" class="truncate text-[13px] font-semibold text-gray-900 hover:underline">${escapeHtml(order.order_number)}</a>
                ${statusBadge(order.status, order.status_label)}
            </div>
            <p class="mt-1 truncate text-[12px] text-gray-700">${escapeHtml(order.customer_name)}</p>
            <p class="mt-0.5 text-[11px] text-gray-500">${escapeHtml(String(order.item_count))} items · ${escapeHtml(order.created_at)}</p>
            <div class="mt-2 flex flex-wrap gap-1">
                ${order.customer_source_label ? `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${order.customer_source_badge_color}">${escapeHtml(order.customer_source_icon ?? '')} ${escapeHtml(order.customer_source_label)}</span>` : ''}
                ${order.has_shortage ? badge(`Short ${order.shortage_qty}`, 'bg-amber-100 text-amber-800') : ''}
                ${order.po_number ? badge(order.po_number, order.has_draft_po ? 'bg-gray-100 text-gray-700' : 'bg-blue-100 text-blue-800') : ''}
            </div>
        </article>
    `;
}

function statusBadge(status, label) {
    const classes = {
        pending: 'bg-gray-100 text-gray-700',
        reserved: 'bg-blue-100 text-blue-800',
        partially_reserved: 'bg-amber-100 text-amber-800',
        fulfilled: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };

    return badge(label, classes[status] ?? 'bg-gray-100 text-gray-700');
}

function badge(label, classes) {
    return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes}">${escapeHtml(label)}</span>`;
}

function bindDragAndDrop() {
    document.querySelectorAll('.board-card[draggable="true"]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            draggedOrderId = Number(card.dataset.orderId);
            draggedFromStatus = card.dataset.status;
            card.classList.add('opacity-60');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(draggedOrderId));
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-60');
            clearDropHighlights();
            draggedOrderId = null;
            draggedFromStatus = null;
        });
    });

    document.querySelectorAll('.board-column-body').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            event.preventDefault();
            const targetStatus = column.dataset.status;
            if (!isAllowedDrop(targetStatus)) {
                event.dataTransfer.dropEffect = 'none';
                return;
            }
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('ring-2', 'ring-blue-300', 'ring-inset');
        });

        column.addEventListener('dragleave', () => {
            column.classList.remove('ring-2', 'ring-blue-300', 'ring-inset');
        });

        column.addEventListener('drop', (event) => {
            event.preventDefault();
            clearDropHighlights();

            const targetStatus = column.dataset.status;
            const card = document.querySelector(`.board-card[data-order-id="${draggedOrderId}"]`);

            if (!card || !isAllowedDrop(targetStatus) || targetStatus === draggedFromStatus) {
                return;
            }

            if (targetStatus === 'fulfilled') {
                openActionModal({
                    orderId: draggedOrderId,
                    orderNumber: card.dataset.orderNumber,
                    action: 'fulfill',
                    title: 'Fulfill order?',
                    message: `Fulfill ${card.dataset.orderNumber} and deduct reserved stock?`,
                });
                return;
            }

            if (targetStatus === 'cancelled') {
                openActionModal({
                    orderId: draggedOrderId,
                    orderNumber: card.dataset.orderNumber,
                    action: 'cancel',
                    title: 'Cancel order?',
                    message: `Cancel ${card.dataset.orderNumber} and release reserved stock?`,
                });
            }
        });
    });
}

function isAllowedDrop(targetStatus) {
    if (!draggedOrderId) {
        return false;
    }

    const card = document.querySelector(`.board-card[data-order-id="${draggedOrderId}"]`);
    if (!card) {
        return false;
    }

    const allowed = (card.dataset.allowedTargets ?? '').split(',').filter(Boolean);

    if (!allowed.includes(targetStatus)) {
        return false;
    }

    if (targetStatus === 'fulfilled' && card.dataset.canFulfill !== '1') {
        return false;
    }

    if (targetStatus === 'cancelled' && card.dataset.canCancel !== '1') {
        return false;
    }

    return true;
}

function clearDropHighlights() {
    document.querySelectorAll('.board-column-body').forEach((column) => {
        column.classList.remove('ring-2', 'ring-blue-300', 'ring-inset');
    });
}

function openActionModal({ orderId, orderNumber, action, title, message }) {
    pendingAction = { orderId, orderNumber, action };
    document.getElementById('board-action-title').textContent = title;
    document.getElementById('board-action-message').textContent = message;
    document.getElementById('board-action-modal')?.classList.remove('hidden');
}

function closeActionModal() {
    pendingAction = null;
    document.getElementById('board-action-modal')?.classList.add('hidden');
}

async function confirmPendingAction() {
    if (!pendingAction) {
        return;
    }

    const { orderId, action } = pendingAction;
    const confirmButton = document.getElementById('board-action-confirm');
    const url = action === 'fulfill'
        ? `${boardConfig.fulfillUrlBase}/${orderId}/fulfill`
        : `${boardConfig.cancelUrlBase}/${orderId}/cancel`;

    setButtonLoading(confirmButton, true, 'Working...');

    try {
        await postData(url);
        showToast(action === 'fulfill' ? 'Order fulfilled.' : 'Order cancelled.');
        closeActionModal();
        await loadBoard();
    } catch (error) {
        showToast(error.message || 'Unable to update order.', 'error');
    } finally {
        setButtonLoading(confirmButton, false);
    }
}
