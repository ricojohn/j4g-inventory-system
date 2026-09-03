const POLL_INTERVAL_MS = 8000;

function isMessengerInboxPage() {
    return Boolean(document.querySelector('[data-messenger-inbox]'));
}

function getSelectedConversationId() {
    return document.querySelector('[data-selected-conversation-id]')?.dataset.selectedConversationId || null;
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

function shouldAutoRefresh() {
    const activeTag = document.activeElement?.tagName?.toLowerCase();
    return !['input', 'textarea', 'select'].includes(activeTag);
}

function refreshInbox() {
    if (!shouldAutoRefresh()) {
        return;
    }

    window.location.reload();
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

    let debounceTimer = null;

    const scheduleRefresh = () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(refreshInbox, 1200);
    };

    window.addEventListener('messenger:updated', scheduleRefresh);
    window.addEventListener('focus', async () => {
        const snapshot = await fetchSnapshot();
        if (snapshot?.selectedConversationId && String(snapshot.selectedConversationId) !== String(getSelectedConversationId())) {
            refreshInbox();
        }
    });

    window.setInterval(async () => {
        const snapshot = await fetchSnapshot();
        if (!snapshot) {
            return;
        }

        const selectedConversationId = getSelectedConversationId();
        if (!selectedConversationId || String(snapshot.selectedConversationId) !== String(selectedConversationId)) {
            refreshInbox();
            return;
        }

        const remoteVersion = snapshot.selectedConversation?.updated_at || snapshot.selectedConversation?.last_outbound_at || snapshot.selectedConversation?.last_inbound_at;
        const localVersion = document.querySelector('[data-conversation-version]')?.dataset.conversationVersion;
        if (remoteVersion && remoteVersion !== localVersion) {
            refreshInbox();
        }
    }, POLL_INTERVAL_MS);
};
