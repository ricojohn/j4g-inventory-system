<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col border-r border-gray-200 bg-neutral-100 transition-transform duration-200 lg:static lg:translate-x-0 lg:shrink-0"
    aria-label="Main navigation"
>
    <div class="flex h-12 items-center justify-between border-b border-gray-200 px-3">
        <div class="min-w-0">
            <p class="truncate text-[13px] font-semibold tracking-tight text-gray-900">J4G Inventory</p>
            <p class="truncate text-[11px] text-gray-500">Printing System</p>
        </div>
        <button
            type="button"
            id="sidebar-close"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-gray-500 hover:bg-white hover:text-gray-900 lg:hidden"
            aria-label="Close menu"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    <nav class="flex-1 space-y-0.5 overflow-y-auto p-2">
        @php
            $linkClass = fn (bool $active) => $active
                ? 'bg-white text-gray-900 font-medium shadow-sm'
                : 'text-gray-600 hover:bg-white/80 hover:text-gray-900';
        @endphp
        @can('view dashboard')
            <a href="{{ route('dashboard') }}" class="{{ $linkClass(request()->routeIs('dashboard')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Dashboard
            </a>
        @endcan
        @can('view categories')
            <a href="{{ route('categories.index') }}" class="{{ $linkClass(request()->routeIs('categories.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Categories
            </a>
        @endcan
        @can('view products')
            <a href="{{ route('products.index') }}" class="{{ $linkClass(request()->routeIs('products.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Products
            </a>
        @endcan
        @canany(['view stock history', 'view low stock report', 'view out of stock report'])
            <p class="px-2.5 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">Reports</p>
        @endcanany
        @can('view stock history')
            <a href="{{ route('reports.stock-history') }}" class="{{ $linkClass(request()->routeIs('reports.stock-history')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Stock History
            </a>
        @endcan
        @can('view low stock report')
            <a href="{{ route('reports.low-stock') }}" class="{{ $linkClass(request()->routeIs('reports.low-stock')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Low Stock
            </a>
        @endcan
        @can('view out of stock report')
            <a href="{{ route('reports.out-of-stock') }}" class="{{ $linkClass(request()->routeIs('reports.out-of-stock')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Out of Stock
            </a>
        @endcan
        @canany(['manage users', 'manage roles', 'manage permissions', 'manage sizes'])
            <p class="px-2.5 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">Administration</p>
        @endcanany
        @can('manage users')
            <a href="{{ route('admin.users.index') }}" class="{{ $linkClass(request()->routeIs('admin.users.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Users
            </a>
        @endcan
        @can('manage roles')
            <a href="{{ route('admin.roles.index') }}" class="{{ $linkClass(request()->routeIs('admin.roles.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Roles
            </a>
        @endcan
        @can('manage sizes')
            <a href="{{ route('admin.sizes.index') }}" class="{{ $linkClass(request()->routeIs('admin.sizes.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">
                Sizes
            </a>
        @endcan
    </nav>
</aside>
