@extends('layouts.app')

@section('page-title', 'Stock History')

@section('content')
    <x-ui.page-header title="Stock History" />

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="grid w-full gap-3 md:grid-cols-5">
                <div>
                    <x-ui.label for="movement_type">Movement Type</x-ui.label>
                    <x-ui.select id="movement_type">
                        <option value="">All</option>
                        @foreach (['IN', 'OUT', 'RESERVE', 'RELEASE', 'DAMAGED', 'ADJUSTMENT'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <x-ui.label for="product_id">Product</x-ui.label>
                    <x-ui.select id="product_id">
                        <option value="">All</option>
                    </x-ui.select>
                </div>
                <div>
                    <x-ui.label for="user_id">User</x-ui.label>
                    <x-ui.select id="user_id">
                        <option value="">All</option>
                    </x-ui.select>
                </div>
                <div>
                    <x-ui.label for="date_from">Date From</x-ui.label>
                    <x-ui.input id="date_from" type="date" />
                </div>
                <div>
                    <x-ui.label for="date_to">Date To</x-ui.label>
                    <x-ui.input id="date_to" type="date" />
                </div>
                <div class="md:col-span-5 flex flex-wrap items-end gap-2">
                    <x-ui.select id="stock-history-per-page" class="!w-auto min-w-28">
                        <option value="20">20 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </x-ui.select>
                </div>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="stock-history-table-body" pagination-id="stock-history-pagination">
            <x-slot:head>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Size</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Before/After Stock</th>
                    <th>Before/After Reserved</th>
                    <th>User</th>
                    <th>Remarks</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const productSelect = document.getElementById('product_id');
    const userSelect = document.getElementById('user_id');

    try {
        const response = await fetch(@json(route('reports.stock-history.filter-options')), {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const payload = await response.json();

        if (response.ok) {
            const urlParams = new URLSearchParams(window.location.search);
            const presetProductId = urlParams.get('product_id');

            payload.products.forEach((product) => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                if (presetProductId && String(product.id) === presetProductId) {
                    option.selected = true;
                }
                productSelect.appendChild(option);
            });

            payload.users.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                userSelect.appendChild(option);
            });
        }
    } catch (error) {
        showToast('Unable to load filter options.', 'error');
    }

    const table = initAsyncTable({
        tbodyId: 'stock-history-table-body',
        paginationId: 'stock-history-pagination',
        dataUrl: @json(route('reports.stock-history.data')),
        columnCount: 9,
        emptyMessage: 'No movements found.',
        getParams: () => ({
            movement_type: document.getElementById('movement_type')?.value ?? '',
            product_id: document.getElementById('product_id')?.value ?? '',
            user_id: document.getElementById('user_id')?.value ?? '',
            date_from: document.getElementById('date_from')?.value ?? '',
            date_to: document.getElementById('date_to')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('stock-history-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((movement) => `
            <tr>
                <td>${escapeHtml(movement.created_at)}</td>
                <td>${escapeHtml(movement.product_name)}</td>
                <td>${escapeHtml(movement.size_name)}</td>
                <td>${escapeHtml(movement.movement_type)}</td>
                <td>${escapeHtml(movement.quantity)}</td>
                <td>${escapeHtml(movement.before_stock)} → ${escapeHtml(movement.after_stock)}</td>
                <td>${escapeHtml(movement.before_reserved)} → ${escapeHtml(movement.after_reserved)}</td>
                <td>${escapeHtml(movement.user_name)}</td>
                <td>${escapeHtml(movement.remarks ?? '')}</td>
            </tr>
        `).join(''),
    });

    const reload = () => table.loadData(1);

    table.loadData(1);

    ['movement_type', 'product_id', 'user_id', 'date_from', 'date_to', 'stock-history-per-page'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', reload);
    });
});
</script>
@endpush
