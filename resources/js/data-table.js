export async function fetchTableData(url, params = {}) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });

    const queryString = query.toString();
    const requestUrl = queryString ? `${url}?${queryString}` : url;

    const response = await fetch(requestUrl, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });

    const payload = await response.json();

    if (!response.ok) {
        const message = payload.message || 'Unable to load table data.';
        throw new Error(message);
    }

    return payload;
}

export function showTableLoading(tbody, columnCount = 1) {
    if (!tbody) {
        return;
    }

    const cells = Array.from({ length: columnCount }, () => (
        '<td><div class="h-4 animate-pulse rounded bg-gray-200"></div></td>'
    )).join('');

    tbody.innerHTML = Array.from({ length: 3 }, () => `<tr>${cells}</tr>`).join('');
}

export function showTableEmpty(tbody, message = 'No records found.', columnCount = 1) {
    if (!tbody) {
        return;
    }

    tbody.innerHTML = `
        <tr>
            <td colspan="${columnCount}" class="py-6! text-center text-[13px] text-gray-500">
                ${escapeHtml(message)}
            </td>
        </tr>
    `;
}

export function showTableError(tbody, message = 'Unable to load data.', onRetry = null, columnCount = 1) {
    if (!tbody) {
        return;
    }

    const retryButton = onRetry
        ? `<button type="button" class="table-retry-btn mt-2 text-sm font-medium text-gray-900 underline">Try again</button>`
        : '';

    tbody.innerHTML = `
        <tr>
            <td colspan="${columnCount}" class="py-8! text-center text-sm text-red-600">
                ${escapeHtml(message)}
                ${retryButton}
            </td>
        </tr>
    `;

    if (onRetry) {
        tbody.querySelector('.table-retry-btn')?.addEventListener('click', onRetry);
    }
}

export function renderPagination(container, pagination, onPageChange) {
    if (!container || !pagination) {
        return;
    }

    if (pagination.last_page <= 1) {
        container.innerHTML = '';
        container.classList.add('hidden');

        return;
    }

    container.classList.remove('hidden');

    const pages = buildPageNumbers(pagination.current_page, pagination.last_page);
    const prevDisabled = pagination.current_page <= 1;
    const nextDisabled = pagination.current_page >= pagination.last_page;

    container.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3 text-[13px] text-gray-600">
            <p>Showing page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total)</p>
            <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
                <button type="button" data-page="${pagination.current_page - 1}" class="pagination-btn h-8 rounded-md border border-gray-300 px-2.5 text-[13px] ${prevDisabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50'}" ${prevDisabled ? 'disabled' : ''}>Previous</button>
                ${pages.map((page) => {
                    if (page === '...') {
                        return '<span class="px-2 py-1.5 text-gray-400">...</span>';
                    }

                    const isActive = page === pagination.current_page;

                    return `<button type="button" data-page="${page}" class="pagination-btn h-8 rounded-md border px-2.5 text-[13px] ${isActive ? 'border-slate-900 bg-slate-900 text-white' : 'border-gray-300 hover:bg-gray-50'}">${page}</button>`;
                }).join('')}
                <button type="button" data-page="${pagination.current_page + 1}" class="pagination-btn h-8 rounded-md border border-gray-300 px-2.5 text-[13px] ${nextDisabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50'}" ${nextDisabled ? 'disabled' : ''}>Next</button>
            </nav>
        </div>
    `;

    container.querySelectorAll('.pagination-btn:not([disabled])').forEach((button) => {
        button.addEventListener('click', () => {
            const page = Number(button.dataset.page);
            if (page >= 1 && page <= pagination.last_page) {
                onPageChange(page);
            }
        });
    });
}

export function renderStatusPill(status) {
    const normalized = String(status).toLowerCase();
    const classes = normalized === 'active'
        ? 'bg-green-100 text-green-800'
        : 'bg-gray-100 text-gray-700';
    const label = normalized.charAt(0).toUpperCase() + normalized.slice(1);

    return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes}">${escapeHtml(label)}</span>`;
}

export function renderStockBadge(status, label = null) {
    const statusValue = String(status).replace(/_/g, ' ').toUpperCase();
    const badgeLabel = label ?? statusValue;
    const classes = window.getStatusBadgeClasses?.(status) ?? ['bg-green-100', 'text-green-800'];

    return `<span data-status-badge class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${classes.join(' ')}">${escapeHtml(badgeLabel)}</span>`;
}

export function debounce(fn, ms = 300) {
    let timeoutId;

    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), ms);
    };
}

export function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function initAsyncTable(config) {
    const {
        tbodyId,
        paginationId,
        dataUrl,
        columnCount = 1,
        emptyMessage = 'No records found.',
        renderRows,
        getParams = () => ({}),
        getPerPage = () => 20,
        onLoaded = null,
    } = config;

    let currentPage = 1;
    const tbody = document.getElementById(tbodyId);
    const paginationContainer = paginationId ? document.getElementById(paginationId) : null;

    async function loadData(page = 1) {
        currentPage = page;

        if (!tbody) {
            return;
        }

        showTableLoading(tbody, columnCount);

        try {
            const payload = await fetchTableData(dataUrl, {
                page: currentPage,
                per_page: getPerPage(),
                ...getParams(),
            });

            if (!payload.data?.length) {
                showTableEmpty(tbody, emptyMessage, columnCount);
            } else {
                tbody.innerHTML = renderRows(payload.data);
            }

            if (paginationContainer) {
                renderPagination(paginationContainer, payload.pagination, loadData);
            }

            onLoaded?.(payload);
        } catch (error) {
            showTableError(tbody, error.message || 'Unable to load data.', () => loadData(currentPage), columnCount);
            paginationContainer?.classList.add('hidden');
        }
    }

    return {
        loadData,
        getCurrentPage: () => currentPage,
    };
}

function buildPageNumbers(currentPage, lastPage) {
    if (lastPage <= 7) {
        return Array.from({ length: lastPage }, (_, index) => index + 1);
    }

    const pages = [1];

    if (currentPage > 3) {
        pages.push('...');
    }

    const start = Math.max(2, currentPage - 1);
    const end = Math.min(lastPage - 1, currentPage + 1);

    for (let page = start; page <= end; page += 1) {
        pages.push(page);
    }

    if (currentPage < lastPage - 2) {
        pages.push('...');
    }

    pages.push(lastPage);

    return pages;
}

window.fetchTableData = fetchTableData;
window.showTableLoading = showTableLoading;
window.showTableEmpty = showTableEmpty;
window.showTableError = showTableError;
window.renderPagination = renderPagination;
window.renderStatusPill = renderStatusPill;
window.renderStockBadge = renderStockBadge;
window.debounce = debounce;
window.initAsyncTable = initAsyncTable;
window.escapeHtml = escapeHtml;
