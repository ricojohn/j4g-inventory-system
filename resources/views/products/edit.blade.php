@extends('layouts.app')

@section('page-title', 'Edit Product')

@section('content')
    <x-ui.page-header :title="$product->name">
        <x-slot:actions>
            @can('view inventory')
                <x-ui.button variant="secondary" :href="route('products.inventory', $product)">Manage Inventory</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-4">
        <x-ui.page-card title="Details">
            <form id="product-form" class="space-y-4 p-4">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-ui.label for="name">Name</x-ui.label>
                        <x-ui.input id="name" type="text" :value="$product->name" required />
                    </div>
                    <div>
                        <x-ui.label for="code">Code</x-ui.label>
                        <x-ui.input id="code" type="text" :value="$product->code" required maxlength="16" />
                        <p class="mt-1 text-[11px] text-amber-700">Changing the code rewrites item codes for all colors (sequence preserved).</p>
                    </div>
                    <div class="sm:col-span-2">
                        <x-ui.label for="description">Description</x-ui.label>
                        <x-ui.textarea id="description" rows="3">{{ $product->description }}</x-ui.textarea>
                    </div>
                    <div>
                        <x-ui.label for="status">Status</x-ui.label>
                        <x-ui.select id="status">
                            <option value="active" @selected($product->status === 'active')>Active</option>
                            <option value="inactive" @selected($product->status === 'inactive')>Inactive</option>
                        </x-ui.select>
                    </div>
                </div>
                <x-ui.button type="submit">Save Details</x-ui.button>
            </form>
        </x-ui.page-card>

        <x-ui.page-card title="Sizes">
            <x-slot:toolbar>
                <x-ui.button type="button" id="add-size-btn" size="sm">Add Size</x-ui.button>
            </x-slot:toolbar>
            <x-ui.async-table tbody-id="sizes-table-body" pagination-id="sizes-pagination">
                <x-slot:head>
                    <tr><th>Size</th><th>Sort</th><th>Cells</th><th>Actions</th></tr>
                </x-slot:head>
            </x-ui.async-table>
        </x-ui.page-card>

        <x-ui.page-card title="Colors">
            <x-slot:toolbar>
                <x-ui.button type="button" id="add-color-btn" size="sm">Add Color</x-ui.button>
            </x-slot:toolbar>
            <x-ui.async-table tbody-id="colors-table-body" pagination-id="colors-pagination">
                <x-slot:head>
                    <tr><th>Item Code</th><th>Color</th><th>Color Code</th><th>Sort</th><th>Cells</th><th>Actions</th></tr>
                </x-slot:head>
            </x-ui.async-table>
        </x-ui.page-card>
    </div>

    @include('products.partials.size-modal')
    @include('products.partials.color-modal')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const productId = {{ $product->id }};

    document.getElementById('product-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const response = await postData(`/products/${productId}`, {
                name: document.getElementById('name').value,
                code: document.getElementById('code').value,
                description: document.getElementById('description').value || null,
                status: document.getElementById('status').value,
            }, 'PUT');
            showToast(response.message || 'Product updated.');
        } catch (error) {
            showToast(error.message || 'Unable to save.', 'error');
        }
    });

    const sizesTable = initAsyncTable({
        tbodyId: 'sizes-table-body',
        paginationId: 'sizes-pagination',
        dataUrl: `/products/${productId}/sizes/data`,
        columnCount: 4,
        emptyMessage: 'No sizes yet.',
        renderRows: (rows) => rows.map((size) => `
            <tr>
                <td>${escapeHtml(size.size_name)}</td>
                <td>${escapeHtml(size.sort_order)}</td>
                <td>${escapeHtml(size.cell_count)}</td>
                <td>
                    <button type="button" class="delete-size ui-row-action ui-row-action-danger" data-id="${size.id}">Delete</button>
                </td>
            </tr>
        `).join(''),
    });
    sizesTable.loadData(1);

    const colorsTable = initAsyncTable({
        tbodyId: 'colors-table-body',
        paginationId: 'colors-pagination',
        dataUrl: `/products/${productId}/colors/data`,
        columnCount: 6,
        emptyMessage: 'No colors yet.',
        renderRows: (rows) => rows.map((color) => `
            <tr>
                <td>${escapeHtml(color.item_code)}</td>
                <td>${escapeHtml(color.color_name)}</td>
                <td>${escapeHtml(color.color_code ?? '')}</td>
                <td>${escapeHtml(color.sort_order)}</td>
                <td>${escapeHtml(color.cell_count)}</td>
                <td>
                    <button type="button" class="delete-color ui-row-action ui-row-action-danger" data-id="${color.id}">Delete</button>
                </td>
            </tr>
        `).join(''),
    });
    colorsTable.loadData(1);

    window.refreshProductTables = () => {
        sizesTable.loadData(sizesTable.getCurrentPage());
        colorsTable.loadData(colorsTable.getCurrentPage());
    };

    document.getElementById('sizes-table-body')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-size');
        if (!btn || !confirm('Delete this size?')) return;
        try {
            await postData(`/products/${productId}/sizes/${btn.dataset.id}`, {}, 'DELETE');
            showToast('Size deleted.');
            window.refreshProductTables();
        } catch (error) {
            showToast(error.message || 'Unable to delete.', 'error');
        }
    });

    document.getElementById('colors-table-body')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-color');
        if (!btn || !confirm('Delete this color?')) return;
        try {
            await postData(`/products/${productId}/colors/${btn.dataset.id}`, {}, 'DELETE');
            showToast('Color deleted.');
            window.refreshProductTables();
        } catch (error) {
            showToast(error.message || 'Unable to delete.', 'error');
        }
    });

    initSizeModal(productId);
    initColorModal(productId);
});
</script>
@endpush
