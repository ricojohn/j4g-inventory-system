@extends('layouts.app')

@section('page-title', 'Customers')

@section('content')
    <x-ui.page-header
        eyebrow="Customer and team records"
        title="Customers"
        subtitle="Profiles, contact handles, and order history in one place."
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button :href="route('customers.create')">+ Add customer</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="customers-search" placeholder="Search customers..." class="w-auto! min-w-48" />
                <x-ui.select id="customers-source-filter" class="w-auto! min-w-36">
                    <option value="">All Sources</option>
                    <option value="facebook">Facebook</option>
                    <option value="instagram">Instagram</option>
                    <option value="viber">Viber</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="walk_in">Walk-in</option>
                    <option value="referral">Referral</option>
                    <option value="other">Other</option>
                </x-ui.select>
                <x-ui.select id="customers-per-page" class="w-auto! min-w-28">
                    <option value="20">20 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="customers-table-body" pagination-id="customers-pagination">
            <x-slot:head>
                <tr>
                    <th>Name</th>
                    <th>Handle</th>
                    <th>Contact</th>
                    <th>Source</th>
                    <th>Orders</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canManage = @json($canManage);

    const table = initAsyncTable({
        tbodyId: 'customers-table-body',
        paginationId: 'customers-pagination',
        dataUrl: @json(route('customers.data')),
        columnCount: 6,
        emptyMessage: 'No customers found.',
        getParams: () => ({
            search: document.getElementById('customers-search')?.value ?? '',
            source: document.getElementById('customers-source-filter')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('customers-per-page')?.value ?? 20),
        renderRows: (rows) => rows.map((customer) => `
            <tr>
                <td><a href="${escapeHtml(customer.show_url)}" class="font-medium text-brand hover:underline">${escapeHtml(customer.name)}</a></td>
                <td>${escapeHtml(customer.handle)}</td>
                <td>${escapeHtml(customer.contact)}</td>
                <td>${escapeHtml(customer.source_label)}</td>
                <td>${escapeHtml(String(customer.orders_count))}</td>
                <td class="space-x-2">
                    <a href="${escapeHtml(customer.show_url)}" class="ui-row-action">View</a>
                    ${canManage && customer.edit_url ? `<a href="${escapeHtml(customer.edit_url)}" class="ui-row-action">Edit</a>` : ''}
                    ${canManage && customer.destroy_url ? `<button type="button" class="ui-row-action ui-row-action-danger delete-customer" data-destroy-url="${escapeHtml(customer.destroy_url)}">Delete</button>` : ''}
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('customers-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('customers-source-filter')?.addEventListener('change', () => table.loadData(1));
    document.getElementById('customers-per-page')?.addEventListener('change', () => table.loadData(1));

    document.getElementById('customers-table-body')?.addEventListener('click', async (event) => {
        const button = event.target.closest('.delete-customer');
        if (!button || !canManage) return;
        if (!confirm('Delete this customer?')) return;

        try {
            await postData(button.dataset.destroyUrl, {}, 'DELETE');
            showToast('Customer deleted.');
            table.loadData(table.getCurrentPage());
        } catch (error) {
            showToast(error.message || 'Unable to delete customer.', 'error');
        }
    });
});
</script>
@endpush
