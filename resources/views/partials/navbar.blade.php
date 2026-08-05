@php
    $authUser = auth()->user();
    $userInitials = collect(explode(' ', trim($authUser->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');

    if ($userInitials === '') {
        $userInitials = strtoupper(substr($authUser->email, 0, 1));
    }
@endphp

<header class="sticky top-0 z-30 flex h-12 items-center justify-between gap-3 border-b border-gray-200 bg-white px-5 sm:px-6 md:px-8 lg:px-10 xl:px-12">
    <div class="flex min-w-0 items-center gap-2">
        <button
            type="button"
            id="sidebar-open"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 lg:hidden"
            aria-label="Open menu"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <span class="hidden rounded-md bg-brand-soft px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-brand sm:inline">
            {{ config('app.name') }}
        </span>
        <span class="hidden text-[12px] text-gray-500 md:inline">{{ now()->format('l, j F') }}</span>
    </div>
    <div class="flex shrink-0 items-center gap-2">
        @can('create orders')
            <x-ui.button href="{{ route('orders.create') }}" variant="primary" class="hidden sm:inline-flex">
                + New order
            </x-ui.button>
        @endcan

        <div class="relative">
            <button
                type="button"
                id="notification-bell"
                class="relative inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                aria-label="Notifications"
                aria-expanded="false"
                aria-haspopup="true"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span id="notification-badge" class="absolute -right-1 -top-1 hidden min-w-[1.125rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-semibold leading-[1.125rem] text-white">0</span>
            </button>
            <div
                id="notification-dropdown"
                class="absolute right-0 z-50 mt-1 hidden w-80 rounded-xl border border-gray-200 bg-white shadow-lg"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2">
                    <h2 class="text-[13px] font-semibold text-gray-900">Notifications</h2>
                    <button type="button" id="notification-mark-read" class="text-[12px] font-medium text-gray-600 hover:text-gray-900">
                        Mark all read
                    </button>
                </div>
                <ul id="notification-list" class="max-h-72 overflow-y-auto">
                    <li id="notification-empty" class="px-3 py-6 text-center text-[13px] text-gray-500">No notifications yet.</li>
                </ul>
            </div>
        </div>

        <div class="relative">
            <button
                type="button"
                id="user-menu-button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand text-[12px] font-semibold text-white hover:bg-brand-hover focus:outline-none focus:ring-2 focus:ring-brand/30 focus:ring-offset-2"
                aria-label="Account menu"
                aria-expanded="false"
                aria-haspopup="true"
            >
                {{ $userInitials }}
            </button>
            <div
                id="user-menu-dropdown"
                class="absolute right-0 z-50 mt-1 hidden w-64 rounded-xl border border-gray-200 bg-white shadow-lg"
            >
                <div class="border-b border-gray-200 px-4 py-3">
                    <p class="text-[13px] font-semibold text-gray-900">{{ $authUser->name }}</p>
                    <p class="mt-0.5 truncate text-[12px] text-gray-500">{{ $authUser->email }}</p>
                    @if ($authUser->roles->isNotEmpty())
                        <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">
                            {{ $authUser->roles->pluck('name')->implode(', ') }}
                        </p>
                    @endif
                </div>
                <div class="py-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-[13px] text-gray-700 hover:bg-gray-50"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
