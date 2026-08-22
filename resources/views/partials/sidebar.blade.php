<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col border-r border-white/10 bg-sidebar text-white transition-transform duration-200 lg:static lg:translate-x-0 lg:shrink-0"
    aria-label="Main navigation"
>
    <div class="flex h-12 items-center justify-between border-b border-white/10 px-3">
        <div class="min-w-0">
            <p class="truncate text-[13px] font-semibold tracking-tight text-white">J4G Printing</p>
            <p class="truncate text-[11px] text-white/50">Operations</p>
        </div>
        <button type="button" id="sidebar-close" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-white/60 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Close menu">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
    <nav class="flex-1 space-y-0.5 overflow-y-auto p-2">
        @php
            $linkClass = fn (bool $active) => $active
                ? 'bg-brand/25 text-white font-medium'
                : 'text-white/65 hover:bg-white/10 hover:text-white';
        @endphp

        <p class="px-2.5 pt-2 pb-1 text-[11px] font-medium uppercase tracking-wide text-white/35">Workspace</p>

        @can('view dashboard')
            <a href="{{ route('dashboard') }}" class="{{ $linkClass(request()->routeIs('dashboard')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Today</a>
        @endcan
        @can('view orders')
            <a href="{{ route('orders.index') }}" class="{{ $linkClass(request()->routeIs('orders.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Orders</a>
        @endcan
        @can('view production')
            <a href="{{ route('production.index') }}" class="{{ $linkClass(request()->routeIs('production.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Production</a>
        @endcan
        @can('view products')
            <a href="{{ route('products.index') }}" class="{{ $linkClass(request()->routeIs('products.*') || request()->routeIs('inventory.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Inventory</a>
        @endcan
        @can('view customers')
            <a href="{{ route('customers.index') }}" class="{{ $linkClass(request()->routeIs('customers.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Customers</a>
        @endcan
        @can('view finance')
            <a href="{{ route('finance.index') }}" class="{{ $linkClass(request()->routeIs('finance.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Finance</a>
        @endcan

        @canany(['view supplier orders', 'use ai assistant', 'use ai assistance', 'view stock history', 'view low stock report', 'view out of stock report'])
            <p class="px-2.5 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-white/35">Tools</p>
        @endcanany
        @can('use ai assistant')
            <a href="{{ route('ai.order-assistant.index') }}" class="{{ $linkClass(request()->routeIs('ai.order-assistant.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">AI Order Assistant</a>
        @endcan
        @can('use ai assistance')
            <a href="{{ route('ai.assistance.index') }}" class="{{ $linkClass(request()->routeIs('ai.assistance.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">AI Assistance</a>
        @endcan
        @can('view supplier orders')
            <a href="{{ route('supplier-orders.index') }}" class="{{ $linkClass(request()->routeIs('supplier-orders.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Supplier Orders</a>
        @endcan
        @can('view stock history')
            <a href="{{ route('reports.stock-history') }}" class="{{ $linkClass(request()->routeIs('reports.stock-history')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Stock History</a>
        @endcan
        @can('view low stock report')
            <a href="{{ route('reports.low-stock') }}" class="{{ $linkClass(request()->routeIs('reports.low-stock')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Low Stock</a>
        @endcan
        @can('view out of stock report')
            <a href="{{ route('reports.out-of-stock') }}" class="{{ $linkClass(request()->routeIs('reports.out-of-stock')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Out of Stock</a>
        @endcan

        @canany(['manage users', 'manage roles', 'manage sizes', 'manage colors', 'manage suppliers', 'manage integrations'])
            <p class="px-2.5 pt-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-white/35">Administration</p>
        @endcanany
        @can('manage users')
            <a href="{{ route('admin.users.index') }}" class="{{ $linkClass(request()->routeIs('admin.users.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Users</a>
        @endcan
        @can('manage roles')
            <a href="{{ route('admin.roles.index') }}" class="{{ $linkClass(request()->routeIs('admin.roles.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Roles</a>
        @endcan
        @can('manage sizes')
            <a href="{{ route('admin.sizes.index') }}" class="{{ $linkClass(request()->routeIs('admin.sizes.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Sizes</a>
        @endcan
        @can('manage colors')
            <a href="{{ route('admin.colors.index') }}" class="{{ $linkClass(request()->routeIs('admin.colors.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Colors</a>
        @endcan
        @can('manage suppliers')
            <a href="{{ route('admin.suppliers.index') }}" class="{{ $linkClass(request()->routeIs('admin.suppliers.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Suppliers</a>
        @endcan
        @can('manage integrations')
            <a href="{{ route('integrations.index') }}" class="{{ $linkClass(request()->routeIs('integrations.*')) }} flex h-9 items-center rounded-md px-2.5 text-[13px]">Integrations</a>
        @endcan
    </nav>
</aside>
