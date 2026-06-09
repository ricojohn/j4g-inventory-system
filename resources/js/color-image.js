import { escapeHtml } from './data-table';

const PLACEHOLDER_ICON = `<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-400" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 9h.008v.008H18V9zm.75 9.75H5.25A2.25 2.25 0 013 16.5V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25z" />
    </svg>
</span>`;

export function renderColorImageTrigger({ imageUrl = '', colorName = '', itemCode = '', subtitle = null }) {
    const resolvedSubtitle = subtitle ?? (itemCode ? `${itemCode} · ${colorName}` : colorName);
    const thumb = imageUrl
        ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(colorName)}" class="h-9 w-9 shrink-0 rounded object-cover ring-1 ring-gray-200">`
        : PLACEHOLDER_ICON;

    return `
        <button
            type="button"
            class="color-image-view-trigger flex items-center gap-2 text-left hover:opacity-80"
            data-image-url="${escapeHtml(imageUrl)}"
            data-subtitle="${escapeHtml(resolvedSubtitle)}"
            title="View color image"
        >
            ${thumb}
            <span class="text-gray-700">${escapeHtml(colorName)}</span>
        </button>
    `;
}

export function renderColorImageIconButton({ imageUrl = '', colorName = '', itemCode = '', subtitle = null, disabled = false }) {
    const resolvedSubtitle = subtitle ?? (itemCode ? `${itemCode} · ${colorName}` : colorName);
    const disabledAttr = disabled ? 'disabled' : '';
    const disabledClass = disabled ? 'cursor-not-allowed opacity-40' : 'hover:bg-gray-100';

    return `
        <button
            type="button"
            class="color-image-view-trigger inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-gray-200 text-gray-500 ${disabledClass}"
            data-image-url="${escapeHtml(imageUrl)}"
            data-subtitle="${escapeHtml(resolvedSubtitle)}"
            title="View color image"
            ${disabledAttr}
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 9h.008v.008H18V9zm.75 9.75H5.25A2.25 2.25 0 013 16.5V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25z" />
            </svg>
        </button>
    `;
}

export function openColorImageViewModal({ imageUrl = '', subtitle = '' }) {
    const modal = document.getElementById('color-image-view-modal');
    const preview = document.getElementById('color-image-view-preview');
    const empty = document.getElementById('color-image-view-empty');
    const subtitleEl = document.getElementById('color-image-view-subtitle');

    if (!modal || !preview || !empty) {
        return;
    }

    if (subtitleEl) {
        subtitleEl.textContent = subtitle;
    }

    if (imageUrl) {
        preview.src = imageUrl;
        preview.classList.remove('hidden');
        empty.classList.add('hidden');
    } else {
        preview.src = '';
        preview.classList.add('hidden');
        empty.classList.remove('hidden');
    }

    modal.classList.remove('hidden');
}

export function initColorImageViewModal() {
    const modal = document.getElementById('color-image-view-modal');

    if (!modal || modal.dataset.initialized === 'true') {
        return;
    }

    modal.dataset.initialized = 'true';

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });

    modal.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => modal.classList.add('hidden'));
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.color-image-view-trigger');

        if (!trigger || trigger.disabled) {
            return;
        }

        event.preventDefault();
        openColorImageViewModal({
            imageUrl: trigger.dataset.imageUrl ?? '',
            subtitle: trigger.dataset.subtitle ?? '',
        });
    });
}

window.renderColorImageTrigger = renderColorImageTrigger;
window.renderColorImageIconButton = renderColorImageIconButton;
window.openColorImageViewModal = openColorImageViewModal;
window.initColorImageViewModal = initColorImageViewModal;
