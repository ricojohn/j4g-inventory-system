@extends('layouts.app')

@section('page-title', 'Roles')

@section('content')
    <x-ui.page-header title="Roles" />

    <div id="roles-grid" class="space-y-4"></div>
    <div id="roles-pagination" class="mt-4 hidden"></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('roles-grid');
    const paginationContainer = document.getElementById('roles-pagination');
    let currentPage = 1;

    async function loadRoles(page = 1) {
        currentPage = page;
        grid.innerHTML = '<p class="text-sm text-gray-500">Loading roles...</p>';

        try {
            const perPage = 20;
            const payload = await fetchTableData(@json(route('admin.roles.data')), {
                page,
                per_page: perPage,
                search: '',
            });

            if (!payload.data.length) {
                grid.innerHTML = `
                    <div class="rounded-2xl border border-gray-200 bg-white px-6 py-10 text-center">
                        <h3 class="text-base font-semibold tracking-tight text-gray-900">No roles found</h3>
                        <p class="mt-1 text-sm text-gray-500">Roles will appear here once configured.</p>
                    </div>
                `;
                paginationContainer.classList.add('hidden');

                return;
            }

            grid.innerHTML = payload.data.map((role) => `
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-[13px] font-semibold text-gray-900">${escapeHtml(role.name)}</h2>
                            <p class="mt-0.5 text-[13px] text-gray-500">${escapeHtml(role.permission_count)} permissions assigned</p>
                        </div>
                        <a href="${escapeHtml(role.edit_url)}" class="ui-row-action border border-gray-300 bg-white">Edit Permissions</a>
                    </div>
                </div>
            `).join('');

            renderPagination(paginationContainer, payload.pagination, loadRoles);
        } catch (error) {
            grid.innerHTML = `<p class="text-sm text-red-600">${escapeHtml(error.message || 'Unable to load roles.')}</p>`;
            paginationContainer.classList.add('hidden');
        }
    }

    loadRoles(1);
});
</script>
@endpush
