<x-ui.page-card class="mx-auto max-w-3xl">
    <form id="product-form" class="space-y-4 p-4 md:p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <x-ui.label for="product_category_id">Category</x-ui.label>
                <x-ui.select id="product_category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('product_category_id', $product?->product_category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <div>
                <x-ui.label>Item Code</x-ui.label>
                @if ($product)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 min-h-11 flex items-center">
                        {{ $product->item_code }}
                    </div>
                @else
                    <div id="item-code-preview" class="flex min-h-11 items-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700">
                        Select a category to preview code
                    </div>
                @endif
            </div>
            <div>
                <x-ui.label for="name">Name</x-ui.label>
                <x-ui.input id="name" type="text" value="{{ old('name', $product?->name) }}" required />
            </div>
            <div>
                <x-ui.label for="color">Color</x-ui.label>
                <x-ui.input id="color" type="text" value="{{ old('color', $product?->color) }}" required />
            </div>
            <div class="md:col-span-2">
                <x-ui.label for="description">Description</x-ui.label>
                <x-ui.textarea id="description" rows="3">{{ old('description', $product?->description) }}</x-ui.textarea>
            </div>
            <div>
                <x-ui.label for="status">Status</x-ui.label>
                <x-ui.select id="status">
                    <option value="active" @selected(old('status', $product?->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $product?->status) === 'inactive')>Inactive</option>
                </x-ui.select>
            </div>
        </div>

        <div>
            <x-ui.label>Sizes (Variants)</x-ui.label>
            <div id="size-options" class="mt-2 grid grid-cols-3 gap-2 md:grid-cols-5"></div>
            <p id="size-options-empty" class="hidden text-sm text-amber-700">
                No sizes configured for this category. Manage Sizes on the Categories page.
            </p>
        </div>

        @if ($product)
            <div class="overflow-hidden rounded-lg border border-gray-200">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700">Variant Stock (use Inventory to change stock)</div>
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Stock</th>
                            <th>Reserved</th>
                            <th>Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->variants as $variant)
                            <tr>
                                <td>{{ $variant->size->name }}</td>
                                <td>{{ $variant->stock_quantity }}</td>
                                <td>{{ $variant->reserved_quantity }}</td>
                                <td>{{ $variant->available_stock }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="route('products.index')">Cancel</x-ui.button>
            <x-ui.button type="submit">Save Product</x-ui.button>
        </div>
    </form>
</x-ui.page-card>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('product-form');
    const categorySelect = document.getElementById('product_category_id');
    const previewEl = document.getElementById('item-code-preview');
    const sizeOptionsEl = document.getElementById('size-options');
    const sizeOptionsEmptyEl = document.getElementById('size-options-empty');
    const previewUrl = @json(route('products.preview-item-code'));
    const variantOptionsUrlTemplate = @json(route('categories.variant-options', ['category' => '__CATEGORY__']));
    const isCreateForm = @json($product === null);
    const initialSelectedSizeIds = @json($selectedSizeIds ?? []);
    let selectedSizeIds = [...initialSelectedSizeIds];

    function variantOptionsUrl(categoryId) {
        return variantOptionsUrlTemplate.replace('__CATEGORY__', categoryId);
    }

    function renderSizeOptions(sizes, preserveSelections = false) {
        if (!sizeOptionsEl) {
            return;
        }

        sizeOptionsEl.innerHTML = '';

        if (sizes.length === 0) {
            sizeOptionsEmptyEl?.classList.remove('hidden');
            return;
        }

        sizeOptionsEmptyEl?.classList.add('hidden');

        sizes.forEach((size) => {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'size_ids[]';
            input.value = String(size.id);
            input.checked = true;

            // if (preserveSelections && selectedSizeIds.includes(size.id)) {
                // input.checked = true;
            // }

            label.appendChild(input);
            label.appendChild(document.createTextNode(size.name));
            sizeOptionsEl.appendChild(label);
        });
    }

    async function loadVariantOptions(preserveSelections = false) {
        if (!categorySelect?.value || !sizeOptionsEl) {
            return;
        }

        if (!preserveSelections) {
            selectedSizeIds = [];
        }

        sizeOptionsEl.innerHTML = '<p class="col-span-full text-sm text-gray-500">Loading sizes...</p>';
        sizeOptionsEmptyEl?.classList.add('hidden');

        try {
            const response = await fetch(variantOptionsUrl(categorySelect.value), {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            renderSizeOptions(payload.sizes ?? [], preserveSelections);
        } catch (error) {
            sizeOptionsEl.innerHTML = '';
            showToast(error.message || 'Unable to load sizes for this category.', 'error');
        }
    }

    async function loadItemCodePreview() {
        if (!previewEl || !categorySelect?.value) {
            return;
        }

        previewEl.textContent = 'Loading preview...';

        try {
            const response = await fetch(`${previewUrl}?category_id=${categorySelect.value}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            previewEl.textContent = payload.item_code;
        } catch (error) {
            previewEl.textContent = 'Unable to preview item code';
            showToast(error.message || 'Unable to preview item code.', 'error');
        }
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            loadVariantOptions(!isCreateForm);
            if (isCreateForm && previewEl) {
                loadItemCodePreview();
            }
        });

        loadVariantOptions(!isCreateForm);

        if (isCreateForm && previewEl) {
            loadItemCodePreview();
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const sizeIds = [...form.querySelectorAll('input[name="size_ids[]"]:checked')].map((input) => Number(input.value));
        if (sizeIds.length === 0) {
            showToast('Select at least one size.', 'error');
            return;
        }

        const payload = {
            product_category_id: Number(document.getElementById('product_category_id').value),
            name: document.getElementById('name').value,
            color: document.getElementById('color').value,
            description: document.getElementById('description').value,
            status: document.getElementById('status').value,
            size_ids: sizeIds,
        };

        try {
            const response = await postData(@json($action), payload, @json($method));
            showToast(response.message);
            window.location.href = response.redirect;
        } catch (error) {
            showToast(error.message || 'Unable to save product.', 'error');
        }
    });
});
</script>
@endpush
