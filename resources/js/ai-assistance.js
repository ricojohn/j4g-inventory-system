const config = window.assistanceConfig || { connected: false, urls: {}, suggestions: [] };

const messagesEl = document.getElementById('chat-messages');
const inputEl = document.getElementById('chat-input');
const sendBtn = document.getElementById('send-btn');
const newChatBtn = document.getElementById('new-chat-btn');
const statusEl = document.getElementById('chat-status');
const errorEl = document.getElementById('chat-error');

/** @type {Array<{role: string, content: string, rows?: Array<Record<string, unknown>>}>} */
let history = [];

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function setBusy(busy) {
    if (sendBtn) {
        sendBtn.disabled = busy || !config.connected;
    }
    if (inputEl) {
        inputEl.disabled = busy || !config.connected;
    }
    document.querySelectorAll('.suggestion-chip').forEach((chip) => {
        chip.disabled = busy || !config.connected;
    });
    if (statusEl) {
        statusEl.classList.toggle('hidden', !busy);
    }
}

function showError(message) {
    if (!errorEl) {
        return;
    }

    if (!message) {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        return;
    }

    errorEl.textContent = message;
    errorEl.classList.remove('hidden');
}

function scrollToBottom() {
    if (messagesEl) {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatAnswer(text) {
    const escaped = escapeHtml(text);
    return escaped
        .replace(/^### (.+)$/gm, '<strong class="block mt-2">$1</strong>')
        .replace(/^## (.+)$/gm, '<strong class="block mt-2 text-[14px]">$1</strong>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/^- (.+)$/gm, '<div class="pl-2">• $1</div>')
        .replace(/\n/g, '<br>');
}

function appendMessage(role, content, rows = []) {
    if (!messagesEl) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = role === 'user'
        ? 'ml-8 rounded-lg bg-brand/10 px-3 py-2 text-[13px] text-gray-900'
        : 'mr-8 rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] text-gray-800';

    const label = document.createElement('p');
    label.className = 'mb-1 text-[11px] font-medium uppercase tracking-wide text-gray-400';
    label.textContent = role === 'user' ? 'You' : 'Assistant';
    wrapper.appendChild(label);

    const body = document.createElement('div');
    body.className = 'leading-relaxed';
    body.innerHTML = role === 'assistant' ? formatAnswer(content) : escapeHtml(content).replace(/\n/g, '<br>');
    wrapper.appendChild(body);

    if (role === 'assistant') {
        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-2';

        const csvBtn = document.createElement('button');
        csvBtn.type = 'button';
        csvBtn.className = 'inline-flex h-8 items-center rounded-md border border-gray-200 bg-white px-2.5 text-[12px] font-medium text-gray-700 hover:bg-gray-50';
        csvBtn.textContent = 'Export CSV';
        csvBtn.addEventListener('click', () => exportReport('csv', content, rows));

        const pdfBtn = document.createElement('button');
        pdfBtn.type = 'button';
        pdfBtn.className = 'inline-flex h-8 items-center rounded-md border border-gray-200 bg-white px-2.5 text-[12px] font-medium text-gray-700 hover:bg-gray-50';
        pdfBtn.textContent = 'Export PDF';
        pdfBtn.addEventListener('click', () => exportReport('pdf', content, rows));

        actions.appendChild(csvBtn);
        actions.appendChild(pdfBtn);
        wrapper.appendChild(actions);
    }

    messagesEl.appendChild(wrapper);
    scrollToBottom();
}

async function exportReport(format, answer, rows) {
    showError('');

    const url = format === 'csv' ? config.urls.exportCsv : config.urls.exportPdf;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: format === 'csv' ? 'text/csv' : 'application/pdf',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                answer,
                rows: Array.isArray(rows) ? rows : [],
                title: 'AI Assistance Report',
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Export failed.');
        }

        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match?.[1] || `ai-assistance.${format}`;

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    } catch (error) {
        showError(error instanceof Error ? error.message : 'Export failed.');
    }
}

async function sendMessage(rawMessage) {
    const message = String(rawMessage || '').trim();

    if (!message || !config.connected) {
        return;
    }

    showError('');
    appendMessage('user', message);
    setBusy(true);

    const historyPayload = history.slice(-10).map(({ role, content }) => ({ role, content }));

    try {
        const response = await fetch(config.urls.ask, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                message,
                history: historyPayload,
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Unable to get an AI response.');
        }

        const answer = payload.answer || '';
        const rows = Array.isArray(payload.rows) ? payload.rows : [];

        history.push({ role: 'user', content: message });
        history.push({ role: 'assistant', content: answer, rows });

        appendMessage('assistant', answer, rows);
        if (inputEl) {
            inputEl.value = '';
        }
    } catch (error) {
        showError(error instanceof Error ? error.message : 'Unable to get an AI response.');
    } finally {
        setBusy(false);
    }
}

function resetChat() {
    history = [];
    showError('');

    if (!messagesEl) {
        return;
    }

    messagesEl.innerHTML = `
        <div class="rounded-lg bg-gray-50 px-3 py-2 text-[13px] text-gray-600" data-role="system-intro">
            Ask about inventory, orders, finance, production, suppliers, or customers. Answers stay grounded in live system data.
        </div>
    `;
}

sendBtn?.addEventListener('click', () => sendMessage(inputEl?.value || ''));

inputEl?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage(inputEl.value || '');
    }
});

newChatBtn?.addEventListener('click', resetChat);

document.querySelectorAll('.suggestion-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        const prompt = chip.getAttribute('data-prompt') || '';
        if (inputEl) {
            inputEl.value = prompt;
            inputEl.focus();
        }
        sendMessage(prompt);
    });
});
