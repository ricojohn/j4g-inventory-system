<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @auth
        <script>window.currentUserId = @json(auth()->id());</script>
        @if (config('broadcasting.default') === 'pusher' && config('broadcasting.connections.pusher.key'))
            <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
            <script>
                window.pusherKey = @json(config('broadcasting.connections.pusher.key'));
                window.pusherCluster = @json(config('broadcasting.connections.pusher.options.cluster'));
            </script>
        @endif
    @endauth
</head>
<body class="min-h-screen bg-surface font-sans text-gray-900 antialiased">
    @auth
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 hidden bg-gray-900/40 lg:hidden" aria-hidden="true"></div>
        <div class="flex min-h-screen">
            @include('partials.sidebar')
            <div class="flex min-h-screen min-w-0 flex-1 flex-col overflow-hidden">
                @include('partials.navbar')
                <main class="min-h-0 w-full flex-1 overflow-hidden px-5 py-5 sm:px-6 md:px-8 lg:px-10 xl:px-12">
                    @include('partials.alerts')
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <main>
            @include('partials.alerts')
            @yield('content')
        </main>
    @endauth
    @include('partials.toast')
    @auth
        <x-ui.color-image-view-modal />
    @endauth
    @stack('scripts')
</body>
</html>
