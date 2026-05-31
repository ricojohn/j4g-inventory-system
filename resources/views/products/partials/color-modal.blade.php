<div id="color-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true">
    <div class="ui-modal-panel flex max-h-[90vh] max-w-md flex-col overflow-hidden">
        <div class="ui-modal-header shrink-0">
            <h2 class="text-[13px] font-semibold text-gray-900">Add Color</h2>
        </div>
        <div class="flex shrink-0 gap-2 border-b border-gray-200 px-4 pt-3">
            <button type="button" class="color-tab active text-[13px] font-medium" data-tab="pick">Pick existing</button>
            <button type="button" class="color-tab text-[13px] text-gray-500" data-tab="new">Add new</button>
        </div>
        <div class="flex min-h-0 flex-1 flex-col gap-3 p-4">
            <div id="color-tab-pick" class="flex min-h-0 flex-1 flex-col gap-2">
                <x-ui.input id="color-pick-search" type="search" placeholder="Search colors..." />
                <div id="color-pick-list" class="flex min-h-0 max-h-64 flex-1 flex-col gap-1 overflow-y-auto rounded border border-gray-200 p-2 text-[13px]">
                    <p class="text-gray-500">Loading...</p>
                </div>
                <p id="color-pick-count" class="text-[11px] text-gray-500">0 selected</p>
            </div>
            <div id="color-tab-new" class="hidden flex min-h-0 flex-1 flex-col gap-2">
                <x-ui.label for="color-bulk">One color per line</x-ui.label>
                <x-ui.textarea id="color-bulk" rows="8" placeholder="BLACK&#10;WHITE" class="flex-1"></x-ui.textarea>
            </div>
        </div>
        <div class="ui-modal-footer shrink-0">
            <x-ui.button type="button" variant="secondary" data-close="color-modal">Cancel</x-ui.button>
            <x-ui.button type="button" id="color-save-btn">Save</x-ui.button>
        </div>
    </div>
</div>

<script>
function initColorModal(productId) {
    const modal = document.getElementById('color-modal');
    let activeTab = 'pick';
    let allColors = [];

    async function loadSuggestions() {
        const response = await fetch(`/products/colors/suggestions?exclude_product_id=${productId}`);
        const { data } = await response.json();
        allColors = data || [];
        renderPickList();
    }

    function renderPickList(filter = '') {
        const list = document.getElementById('color-pick-list');
        const term = filter.trim().toLowerCase();
        const filtered = term
            ? allColors.filter((c) => c.name.toLowerCase().includes(term))
            : allColors;

        if (!filtered.length) {
            list.innerHTML = '<p class="text-gray-500">No colors available to pick.</p>';
            updatePickCount();
            return;
        }

        list.innerHTML = filtered.map((color) => `
            <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-0.5 hover:bg-gray-50">
                <input type="checkbox" class="color-pick-checkbox rounded border-gray-300" value="${escapeHtml(color.name)}">
                <span>${escapeHtml(color.name)}</span>
            </label>
        `).join('');

        list.querySelectorAll('.color-pick-checkbox').forEach((cb) => {
            cb.addEventListener('change', updatePickCount);
        });
        updatePickCount();
    }

    function updatePickCount() {
        const count = modal.querySelectorAll('.color-pick-checkbox:checked').length;
        document.getElementById('color-pick-count').textContent = `${count} selected`;
    }

    document.getElementById('add-color-btn')?.addEventListener('click', async () => {
        activeTab = 'pick';
        modal.querySelectorAll('.color-tab').forEach((b) => b.classList.toggle('active', b.dataset.tab === 'pick'));
        document.getElementById('color-tab-pick').classList.remove('hidden');
        document.getElementById('color-tab-new').classList.add('hidden');
        document.getElementById('color-pick-search').value = '';
        document.getElementById('color-bulk').value = '';
        modal.classList.remove('hidden');
        await loadSuggestions();
    });

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });
    modal?.querySelectorAll('[data-close="color-modal"]').forEach((el) => el.addEventListener('click', () => modal.classList.add('hidden')));

    modal?.querySelectorAll('.color-tab').forEach((btn) => btn.addEventListener('click', () => {
        activeTab = btn.dataset.tab;
        modal.querySelectorAll('.color-tab').forEach((b) => {
            b.classList.toggle('active', b.dataset.tab === activeTab);
            b.classList.toggle('text-gray-500', b.dataset.tab !== activeTab);
            b.classList.toggle('font-medium', b.dataset.tab === activeTab);
        });
        document.getElementById('color-tab-pick').classList.toggle('hidden', activeTab !== 'pick');
        document.getElementById('color-tab-new').classList.toggle('hidden', activeTab !== 'new');
    }));

    document.getElementById('color-pick-search')?.addEventListener('input', (e) => renderPickList(e.target.value));

    document.getElementById('color-save-btn')?.addEventListener('click', async () => {
        try {
            let response;
            if (activeTab === 'pick') {
                const names = [...modal.querySelectorAll('.color-pick-checkbox:checked')].map((cb) => cb.value);
                if (!names.length) {
                    showToast('Select at least one color.', 'error');
                    return;
                }
                response = await postData(`/products/${productId}/colors/bulk`, {
                    colors: names.map((name) => ({ color_name: name })),
                });
            } else {
                const names = document.getElementById('color-bulk').value.split('\n').map((s) => s.trim()).filter(Boolean);
                if (!names.length) {
                    showToast('Enter at least one color.', 'error');
                    return;
                }
                response = await postData(`/products/${productId}/colors/bulk`, {
                    colors: names.map((name) => ({ color_name: name })),
                });
            }
            showToast(response.message || `${response.created} color(s) added.`);
            modal.classList.add('hidden');
            window.refreshProductTables?.();
        } catch (error) {
            showToast(error.message || 'Unable to add color.', 'error');
        }
    });
}
</script>
