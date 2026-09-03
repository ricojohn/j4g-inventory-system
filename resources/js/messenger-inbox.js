const POLL_INTERVAL_MS = 5000;

function isMessengerInboxPage() {
    return Boolean(document.querySelector('[data-messenger-inbox]'));
}

function getSelectedConversationId() {
    return document.querySelector('[data-selected-conversation-id]')?.dataset.selectedConversationId || null;
}

function sanitize(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatTime(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

function renderMessage(message) {
    const outbound = message.direction === 'outbound';
    const alignment = outbound ? 'justify-end' : 'justify-start';
    const bubble = outbound ? 'bg-brand text-white' : 'bg-gray-100 text-gray-900';
    const meta = outbound ? 'text-brand-soft' : 'text-gray-500';

    return `
        <div class="flex ${alignment}">
            <div class="max-w-[80%] rounded-2xl px-4 py-3 text-[13px] shadow-sm ${bubble}">
                <p class="mb-1 text-[11px] font-medium ${meta}">
                    ${sanitize(message.sender_type)} · ${sanitize(formatTime(message.created_at))}
                </p>
                <p class="whitespace-pre-wrap">${sanitize(message.body || `[${message.message_type}]`)}</p>
            </div>
        </div>
    `;
}

function updateText(selector, value) {
    const element = document.querySelector(selector);
    if (element) {
        element.textContent = value ?? '';
    }
}

function updateInboxFromSnapshot(snapshot) {
    const selected = snapshot?.selectedConversation;
    if (!selected) {
        return;
    }

    updateText('[data-conversation-customer-name]', selected.draft?.customer_name || selected.psid);
    updateText('[data-conversation-page-name]', selected.page_name);
    updateText('[data-conversation-psid]', selected.psid);
    updateText('[data-sidebar-customer-name]', selected.draft?.customer_name || 'Contact details');
    updateText('[data-sidebar-psid]', selected.psid);
    updateText('[data-draft-status]', selected.draft?.status ? selected.draft.status.replace(/_/g, ' ') : 'N/A');
    updateText('[data-draft-fulfillment]', selected.draft?.fulfillment_method ? selected.draft.fulfillment_method : 'n/a');
    updateText('[data-draft-payment]', selected.draft?.payment_method_preference || 'n/a');
    updateText('[data-draft-address]', selected.draft?.delivery_address || 'n/a');

    const summary = document.querySelector('[data-draft-summary]');
    const summaryEmpty = document.querySelector('[data-draft-summary-empty]');
    if (selected.draft?.summary_text) {
        if (summary) {
            summary.textContent = selected.draft.summary_text;
            summary.classList.remove('hidden');
        } else {
            const summaryContainer = document.querySelector('[data-draft-summary-empty]')?.parentElement;
            if (summaryContainer) {
                summaryContainer.insertAdjacentHTML('afterbegin', `<pre class="max-h-44 overflow-auto whitespace-pre-wrap rounded-xl bg-white p-3 text-[12px] text-gray-700 shadow-sm" data-draft-summary>${sanitize(selected.draft.summary_text)}</pre>`);
            }
        }
        summaryEmpty?.remove();
    } else {
        summary?.remove();
        if (!summaryEmpty) {
            const summaryContainer = document.querySelector('[data-draft-summary]')?.parentElement;
            if (summaryContainer) {
                summaryContainer.insertAdjacentHTML('afterbegin', '<p class="text-[13px] text-gray-500" data-draft-summary-empty>Prepare a final summary to lock the order details before confirmation.</p>');
            }
        }
    }

    const messageList = document.querySelector('[data-message-list]');
    if (messageList) {
        messageList.innerHTML = (selected.messages || []).map(renderMessage).join('');
    }

    const version = document.querySelector('[data-conversation-version]');
    if (version && selected.updated_at) {
        version.dataset.conversationVersion = selected.updated_at;
    }
}

async function fetchSnapshot() {
    const endpoint = document.querySelector('[data-messenger-snapshot-url]')?.dataset.messengerSnapshotUrl;
    const selectedConversationId = getSelectedConversationId();

    if (!endpoint || !selectedConversationId) {
        return null;
    }

    const response = await fetch(endpoint, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return null;
    }

    return response.json();
}

window.initMessengerInbox = function initMessengerInbox() {
    if (!isMessengerInboxPage()) {
        return;
    }

    const search = document.querySelector('[data-conversation-search]');
    const items = Array.from(document.querySelectorAll('[data-conversation-item]'));

    search?.addEventListener('input', (event) => {
        const term = String(event.target.value || '').trim().toLowerCase();
        items.forEach((item) => {
            const text = String(item.dataset.conversationText || '');
            item.classList.toggle('hidden', term.length > 0 && !text.includes(term));
        });
    });

    let refreshPending = false;
    const scheduleRefresh = async () => {
        if (refreshPending) {
            return;
        }

        refreshPending = true;
        window.setTimeout(async () => {
            try {
                const snapshot = await fetchSnapshot();
                if (snapshot) {
                    updateInboxFromSnapshot(snapshot);
                }
            } finally {
                refreshPending = false;
            }
        }, 350);
    };

    window.addEventListener('messenger:updated', scheduleRefresh);

    window.setInterval(async () => {
        const snapshot = await fetchSnapshot();
        if (snapshot) {
            updateInboxFromSnapshot(snapshot);
        }
    }, POLL_INTERVAL_MS);
};
