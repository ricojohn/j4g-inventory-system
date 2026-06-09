import './bootstrap';
import './data-table';
import { initColorImageViewModal } from './color-image';

window.postData = async function postData(url, data = {}, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: method === 'GET' ? undefined : JSON.stringify(data),
    });

    const payload = await response.json();

    if (!response.ok) {
        const message = payload.message
            || (payload.errors ? Object.values(payload.errors).flat()[0] : null)
            || 'Request failed.';
        throw { message, ...payload };
    }

    return payload;
};

window.showToast = function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto rounded-lg border px-3 py-2 text-[13px] shadow-sm ${
        type === 'error'
            ? 'border-red-200 bg-red-50 text-red-700'
            : type === 'warning'
                ? 'border-amber-200 bg-amber-50 text-amber-800'
                : 'border-gray-200 bg-white text-gray-900'
    }`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 4000);
};

window.setButtonLoading = function setButtonLoading(button, isLoading, loadingText = 'Saving...') {
    if (!button) {
        return;
    }

    if (isLoading) {
        button.dataset.originalText = button.textContent;
        button.textContent = loadingText;
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
    } else {
        button.textContent = button.dataset.originalText || button.textContent;
        button.disabled = false;
        button.classList.remove('opacity-60', 'cursor-not-allowed');
    }
};

window.getStatusBadgeClasses = function getStatusBadgeClasses(status) {
    const normalized = String(status).replace(/_/g, ' ').toUpperCase();

    if (normalized === 'OUT OF STOCK') {
        return ['bg-red-100', 'text-red-800'];
    }

    if (normalized === 'LOW STOCK') {
        return ['bg-amber-100', 'text-amber-800'];
    }

    if (normalized === 'RESERVED') {
        return ['bg-blue-100', 'text-blue-800'];
    }

    if (normalized === 'DAMAGED') {
        return ['bg-gray-200', 'text-gray-800'];
    }

    return ['bg-green-100', 'text-green-800'];
};

window.updateStatusBadge = function updateStatusBadge(element, status) {
    if (!element) {
        return;
    }

    element.textContent = String(status).replace(/_/g, ' ');
    element.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium';
    getStatusBadgeClasses(status).forEach((className) => element.classList.add(className));
};

window.highlightRow = function highlightRow(row) {
    if (!row) {
        return;
    }

    row.classList.add('bg-yellow-50');
    setTimeout(() => row.classList.remove('bg-yellow-50'), 2000);
};

window.updateVariantRow = function updateVariantRow(data) {
    const row = document.querySelector(`[data-variant-id="${data.variant_id}"]`);
    if (!row) {
        return;
    }

    const stockCell = row.querySelector('[data-stock-quantity]');
    const reservedCell = row.querySelector('[data-reserved-quantity]');
    const availableCell = row.querySelector('[data-available-stock]');
    const statusBadge = row.querySelector('[data-status-badge]');

    if (stockCell) {
        stockCell.textContent = data.stock_quantity;
    }
    if (reservedCell) {
        reservedCell.textContent = data.reserved_quantity;
    }
    if (availableCell) {
        availableCell.textContent = data.available_stock;
    }
    if (statusBadge) {
        updateStatusBadge(statusBadge, data.status);
    }

    highlightRow(row);
};

window.initSidebar = function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const openButton = document.getElementById('sidebar-open');
    const closeButton = document.getElementById('sidebar-close');

    if (!sidebar || !backdrop || !openButton) {
        return;
    }

    const openSidebar = () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeSidebar = () => {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    openButton.addEventListener('click', openSidebar);
    closeButton?.addEventListener('click', closeSidebar);
    backdrop.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            closeSidebar();
        }
    });
};

window.initPusher = function initPusher() {
    if (typeof Pusher === 'undefined' || !window.pusherKey) {
        return;
    }

    const pusher = new Pusher(window.pusherKey, {
        cluster: window.pusherCluster,
    });

    const channel = pusher.subscribe('inventory');
    channel.bind('stock.updated', (data) => {
        updateVariantRow(data);
        pushNotification(data);
        showStockToast(data);
        window.dispatchEvent(new CustomEvent('inventory:updated', { detail: data }));
    });
};

const NOTIFICATIONS_STORAGE_KEY = 'j4g.notifications';
const NOTIFICATIONS_UNREAD_KEY = 'j4g.notifications.unread';
const MAX_NOTIFICATIONS = 20;

function getStoredNotifications() {
    try {
        const raw = sessionStorage.getItem(NOTIFICATIONS_STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function saveStoredNotifications(notifications) {
    sessionStorage.setItem(NOTIFICATIONS_STORAGE_KEY, JSON.stringify(notifications.slice(0, MAX_NOTIFICATIONS)));
}

function getUnreadCount() {
    const raw = sessionStorage.getItem(NOTIFICATIONS_UNREAD_KEY);
    return raw ? Number(raw) : 0;
}

function setUnreadCount(count) {
    sessionStorage.setItem(NOTIFICATIONS_UNREAD_KEY, String(Math.max(0, count)));
}

function formatNotificationMessage(data) {
    return `${data.user_name} — ${data.movement_type} ${data.quantity} of ${data.product_name} (${data.size_name})`;
}

function renderNotificationItem(data) {
    const item = document.createElement('li');
    item.className = 'border-b border-gray-100 px-3 py-2 text-[13px] last:border-b-0';
    item.dataset.movementId = String(data.movement_id ?? '');

    const message = document.createElement('p');
    message.className = 'font-medium text-gray-900';
    message.textContent = formatNotificationMessage(data);

    const meta = document.createElement('p');
    meta.className = 'mt-0.5 text-[11px] text-gray-500';
    meta.textContent = data.created_at_human ?? '';

    item.appendChild(message);
    item.appendChild(meta);

    return item;
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (!badge) {
        return;
    }

    if (count <= 0) {
        badge.classList.add('hidden');
        badge.textContent = '0';
        return;
    }

    badge.classList.remove('hidden');
    badge.textContent = count > 99 ? '99+' : String(count);
}

function renderNotificationList(notifications) {
    const list = document.getElementById('notification-list');
    const empty = document.getElementById('notification-empty');
    if (!list) {
        return;
    }

    list.querySelectorAll('li:not(#notification-empty)').forEach((node) => node.remove());

    if (!notifications.length) {
        empty?.classList.remove('hidden');
        return;
    }

    empty?.classList.add('hidden');
    notifications.forEach((notification) => {
        list.appendChild(renderNotificationItem(notification));
    });
}

function loadStoredNotifications() {
    const notifications = getStoredNotifications();
    renderNotificationList(notifications);
    updateNotificationBadge(getUnreadCount());
}

function pushNotification(data) {
    if (Number(data.user_id) === Number(window.currentUserId)) {
        return;
    }

    const notifications = getStoredNotifications();
    notifications.unshift(data);
    saveStoredNotifications(notifications);
    renderNotificationList(notifications);

    const unread = getUnreadCount() + 1;
    setUnreadCount(unread);
    updateNotificationBadge(unread);
}

function showStockToast(data) {
    if (Number(data.user_id) === Number(window.currentUserId)) {
        return;
    }

    if (!data.movement_type || !data.product_name) {
        showToast('Stock updated in real time.');
        return;
    }

    showToast(formatNotificationMessage(data));
}

function clearNotifications() {
    saveStoredNotifications([]);
    setUnreadCount(0);
    renderNotificationList([]);
    updateNotificationBadge(0);
}

function markNotificationsRead() {
    setUnreadCount(0);
    updateNotificationBadge(0);
}

function toggleNotificationDropdown(forceOpen = null) {
    const dropdown = document.getElementById('notification-dropdown');
    const bell = document.getElementById('notification-bell');
    if (!dropdown || !bell) {
        return;
    }

    const shouldOpen = forceOpen ?? dropdown.classList.contains('hidden');

    if (shouldOpen) {
        dropdown.classList.remove('hidden');
        bell.setAttribute('aria-expanded', 'true');
        markNotificationsRead();
    } else {
        dropdown.classList.add('hidden');
        bell.setAttribute('aria-expanded', 'false');
    }
}

window.initNotifications = function initNotifications() {
    loadStoredNotifications();

    const bell = document.getElementById('notification-bell');
    const markRead = document.getElementById('notification-mark-read');

    bell?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleNotificationDropdown();
    });

    markRead?.addEventListener('click', (event) => {
        event.stopPropagation();
        clearNotifications();
        toggleNotificationDropdown(false);
    });

    document.addEventListener('click', (event) => {
        const dropdown = document.getElementById('notification-dropdown');
        const bellButton = document.getElementById('notification-bell');
        if (!dropdown || dropdown.classList.contains('hidden')) {
            return;
        }

        if (!dropdown.contains(event.target) && !bellButton?.contains(event.target)) {
            toggleNotificationDropdown(false);
        }
    });
};

function toggleUserMenu(forceOpen = null) {
    const dropdown = document.getElementById('user-menu-dropdown');
    const button = document.getElementById('user-menu-button');
    if (!dropdown || !button) {
        return;
    }

    const shouldOpen = forceOpen ?? dropdown.classList.contains('hidden');

    if (shouldOpen) {
        dropdown.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');
    } else {
        dropdown.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
    }
}

window.initUserMenu = function initUserMenu() {
    const button = document.getElementById('user-menu-button');

    button?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleUserMenu();
    });

    document.addEventListener('click', (event) => {
        const dropdown = document.getElementById('user-menu-dropdown');
        const trigger = document.getElementById('user-menu-button');
        if (!dropdown || dropdown.classList.contains('hidden')) {
            return;
        }

        if (!dropdown.contains(event.target) && !trigger?.contains(event.target)) {
            toggleUserMenu(false);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initNotifications();
    initUserMenu();
    initPusher();
    initColorImageViewModal();
});
