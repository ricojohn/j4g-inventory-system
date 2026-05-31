<div id="size-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
    <div class="ui-modal-panel flex max-h-[90vh] max-w-md flex-col overflow-hidden">
        <div class="ui-modal-header shrink-0">
            <h2 class="text-[13px] font-semibold text-gray-900">Add Size</h2>
        </div>
        <div class="flex shrink-0 gap-2 border-b border-gray-200 px-4 pt-3">
            <button type="button" class="size-tab active text-[13px] font-medium" data-tab="pick">Pick existing</button>
            <button type="button" class="size-tab text-[13px] text-gray-500" data-tab="new">Add new</button>
        </div>
        <div class="flex min-h-0 flex-1 flex-col gap-3 p-4">
            <div id="size-tab-pick" class="flex min-h-0 flex-1 flex-col gap-2">
                <x-ui.input id="size-pick-search" type="search" placeholder="Search sizes..." />
                <div id="size-pick-list" class="flex min-h-0 max-h-64 flex-1 flex-col gap-1 overflow-y-auto rounded border border-gray-200 p-2 text-[13px]">
                    <p class="text-gray-500">Loading...</p>
                </div>
                <p id="size-pick-count" class="text-[11px] text-gray-500">0 selected</p>
            </div>
            <div id="size-tab-new" class="hidden flex min-h-0 flex-1 flex-col gap-2">
                <x-ui.label for="size-bulk">One size per line</x-ui.label>
                <x-ui.textarea id="size-bulk" rows="8" placeholder="XS&#10;S&#10;M" class="flex-1"></x-ui.textarea>
            </div>
        </div>
        <div class="ui-modal-footer shrink-0">
            <x-ui.button type="button" variant="secondary" data-close="size-modal">Cancel</x-ui.button>
            <x-ui.button type="button" id="size-save-btn">Save</x-ui.button>
        </div>
    </div>
</div>

<script>
function initSizeModal(productId) {
    const modal = document.getElementById('size-modal');
    let activeTab = 'pick';
    let allSizes = [];

    async function loadSuggestions() {
        const response = await fetch(`/products/sizes/suggestions?exclude_product_id=${productId}`);
        const { data } = await response.json();
        allSizes = data || [];
        renderPickList();
    }

    function renderPickList(filter = '') {
        const list = document.getElementById('size-pick-list');
        const term = filter.trim().toLowerCase();
        const filtered = term
            ? allSizes.filter((s) => s.name.toLowerCase().includes(term))
            : allSizes;

        if (!filtered.length) {
            list.innerHTML = '<p class="text-gray-500">No sizes available to pick.</p>';
            updatePickCount();
            return;
        }

        list.innerHTML = filtered.map((size) => `
            <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-gray-50">
                <input type="checkbox" class="size-pick-checkbox rounded border-gray-300" value="${escapeHtml(size.name)}">
                <span>${escapeHtml(size.name)}</span>
            </label>
        `).join('');

        list.querySelectorAll('.size-pick-checkbox').forEach((cb) => {
            cb.addEventListener('change', updatePickCount);
        });
        updatePickCount();
    }

    function updatePickCount() {
        const count = modal.querySelectorAll('.size-pick-checkbox:checked').length;
        document.getElementById('size-pick-count').textContent = `${count} selected`;
    }

    document.getElementById('add-size-btn')?.addEventListener('click', async () => {
        activeTab = 'pick';
        modal.querySelectorAll('.size-tab').forEach((b) => b.classList.toggle('active', b.dataset.tab === 'pick'));
        document.getElementById('size-tab-pick').classList.remove('hidden');
        document.getElementById('size-tab-new').classList.add('hidden');
        document.getElementById('size-pick-search').value = '';
        document.getElementById('size-bulk').value = '';
        modal.classList.remove('hidden');
        await loadSuggestions();
    });

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });
    modal?.querySelectorAll('[data-close="size-modal"]').forEach((el) => el.addEventListener('click', () => modal.classList.add('hidden')));

    modal?.querySelectorAll('.size-tab').forEach((btn) => btn.addEventListener('click', () => {
        activeTab = btn.dataset.tab;
        modal.querySelectorAll('.size-tab').forEach((b) => {
            b.classList.toggle('active', b.dataset.tab === activeTab);
            b.classList.toggle('text-gray-500', b.dataset.tab !== activeTab);
            b.classList.toggle('font-medium', b.dataset.tab === activeTab);
        });
        document.getElementById('size-tab-pick').classList.toggle('hidden', activeTab !== 'pick');
        document.getElementById('size-tab-new').classList.toggle('hidden', activeTab !== 'new');
    }));

    document.getElementById('size-pick-search')?.addEventListener('input', (e) => renderPickList(e.target.value));

    document.getElementById('size-save-btn')?.addEventListener('click', async () => {
        try {
            let response;
            if (activeTab === 'pick') {
                const names = [...modal.querySelectorAll('.size-pick-checkbox:checked')].map((cb) => cb.value);
                if (!names.length) {
                    showToast('Select at least one size.', 'error');
                    return;
                }
                response = await postData(`/products/${productId}/sizes/bulk`, { size_names: names });
            } else {
                const names = document.getElementById('size-bulk').value.split('\n').map((s) => s.trim()).filter(Boolean);
                if (!names.length) {
                    showToast('Enter at least one size.', 'error');
                    return;
                }
                response = await postData(`/products/${productId}/sizes/bulk`, { size_names: names });
            }
            showToast(response.message || `${response.created} size(s) added.`);
            modal.classList.add('hidden');
            window.refreshProductTables?.();
        } catch (error) {
            showToast(error.message || 'Unable to add size.', 'error');
        }
    });
}
</script>
