@extends('layouts.app')

@section('page-title', 'Users')

@section('content')
    <x-ui.page-header title="Users">
        <x-slot:actions>
            <x-ui.button :href="route('admin.users.create')">Add User</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card>
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="users-search" placeholder="Search users..." class="!w-auto min-w-48" />
                <x-ui.select id="users-per-page" class="!w-auto min-w-28">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>

        <x-ui.async-table tbody-id="users-table-body" pagination-id="users-pagination">
            <x-slot:head>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </x-slot:head>
        </x-ui.async-table>
    </x-ui.page-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = initAsyncTable({
        tbodyId: 'users-table-body',
        paginationId: 'users-pagination',
        dataUrl: @json(route('admin.users.data')),
        columnCount: 5,
        emptyMessage: 'No users found.',
        getParams: () => ({
            search: document.getElementById('users-search')?.value ?? '',
        }),
        getPerPage: () => Number(document.getElementById('users-per-page')?.value ?? 25),
        renderRows: (rows) => rows.map((user) => `
            <tr>
                <td>${escapeHtml(user.name)}</td>
                <td>${escapeHtml(user.email)}</td>
                <td>${escapeHtml(user.role)}</td>
                <td>${renderStatusPill(user.status)}</td>
                <td>
                    <a href="${escapeHtml(user.edit_url)}" class="ui-row-action">Edit</a>
                </td>
            </tr>
        `).join(''),
    });

    table.loadData(1);

    document.getElementById('users-search')?.addEventListener('input', debounce(() => table.loadData(1), 300));
    document.getElementById('users-per-page')?.addEventListener('change', () => table.loadData(1));
});
</script>
@endpush
