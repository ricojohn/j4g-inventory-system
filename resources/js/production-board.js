import { escapeHtml, debounce } from './data-table';

let boardConfig = null;

document.addEventListener('DOMContentLoaded', () => {
    boardConfig = window.productionBoardConfig;
    if (!boardConfig) {
        return;
    }

    document.getElementById('production-search')?.addEventListener('input', debounce(() => loadBoard(), 300));
    loadBoard();
});

async function loadBoard() {
    const params = new URLSearchParams({
        search: document.getElementById('production-search')?.value ?? '',
    });

    try {
        const response = await fetch(`${boardConfig.dataUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to load production board.');
        }

        renderColumns(data.columns ?? []);
    } catch (error) {
        const container = document.getElementById('production-columns');
        if (container) {
            container.innerHTML =
                `<p class="text-[13px] text-red-600">${escapeHtml(error.message || 'Unable to load production board.')}</p>`;
        }
    }
}

function renderColumns(columns) {
    const container = document.getElementById('production-columns');
    if (!container) {
        return;
    }

    if (!columns.length) {
        container.innerHTML = '<p class="text-[13px] text-gray-500">No production stages configured.</p>';
        return;
    }

    container.innerHTML = columns.map((column) => `
        <div class="min-w-[240px] max-w-[280px] flex-1 rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-[13px] font-semibold text-gray-900">${escapeHtml(column.label)}</h3>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">${column.count}</span>
                </div>
            </div>
            <div class="space-y-2 p-2">
                ${(column.orders ?? []).length
                    ? column.orders.map((order) => renderCard(order)).join('')
                    : '<p class="px-1 py-3 text-[12px] text-gray-400">No orders</p>'}
            </div>
        </div>
    `).join('');

    container.querySelectorAll('[data-advance]').forEach((button) => {
        button.addEventListener('click', () => advanceOrder(button.dataset.advance));
    });
}

function renderCard(order) {
    const metaBadge = order.production_blocked
        ? '<span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-700">Blocked</span>'
        : (order.due_date
            ? `<span class="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-700">Due ${escapeHtml(order.due_date)}</span>`
            : '');

    return `
        <div class="rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <a href="${escapeHtml(order.show_url)}" class="text-[13px] font-semibold text-brand hover:underline">${escapeHtml(order.order_number)}</a>
                ${metaBadge}
            </div>
            <p class="mt-1 text-[12px] text-gray-700">${escapeHtml(order.customer_name)}</p>
            <p class="mt-1 text-[11px] text-gray-500">${escapeHtml(order.status_label)} · ${escapeHtml(String(order.item_count))} items</p>
            ${order.can_advance
                ? `<button type="button" data-advance="${order.id}" class="mt-2 inline-flex h-8 items-center rounded-md bg-brand px-2.5 text-[12px] font-medium text-white hover:bg-brand-hover">Advance</button>`
                : ''}
        </div>
    `;
}

async function advanceOrder(orderId) {
    if (!confirm('Advance this job to the next production stage?')) {
        return;
    }

    const url = `${boardConfig.advanceUrlBase}/${orderId}/advance`;

    try {
        let data;

        if (typeof window.postData === 'function') {
            data = await window.postData(url);
        } else {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: '{}',
            });
            data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to advance stage.');
            }
        }

        if (typeof showToast === 'function') {
            showToast(data.production_stage_label
                ? `Moved to ${data.production_stage_label}`
                : 'Stage advanced.');
        }

        loadBoard();
    } catch (error) {
        if (typeof showToast === 'function') {
            showToast(error.message || 'Unable to advance stage.', 'error');
        } else {
            alert(error.message || 'Unable to advance stage.');
        }
    }
}
