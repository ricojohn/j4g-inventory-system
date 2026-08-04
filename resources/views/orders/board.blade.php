@extends('layouts.app')

@section('page-title', 'Customer Orders Board')

@push('scripts')
    @vite(['resources/js/orders-board.js'])
@endpush

@section('content')
    <x-ui.page-header
        eyebrow="Operations"
        title="Orders board"
        subtitle="Reservation status, shortages, and next actions."
    >
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-md border border-gray-200 bg-white p-0.5">
                    <a href="{{ route('orders.index') }}" class="rounded px-2.5 py-1 text-[12px] font-medium text-gray-600 hover:bg-gray-50">Table</a>
                    <a href="{{ route('orders.board') }}" class="rounded bg-brand px-2.5 py-1 text-[12px] font-medium text-white">Board</a>
                </div>
                @can('create orders')
                    <x-ui.button :href="route('orders.create')">+ New order</x-ui.button>
                @endcan
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card class="mb-4">
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="board-search" placeholder="Search orders..." class="w-auto! min-w-48" />
                <x-ui.select id="board-source-filter" class="w-auto! min-w-36">
                    <option value="">All Sources</option>
                    <option value="facebook">Facebook</option>
                    <option value="instagram">Instagram</option>
                    <option value="viber">Viber</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="walk_in">Walk-in</option>
                    <option value="referral">Referral</option>
                    <option value="other">Other</option>
                </x-ui.select>
            </div>
        </x-slot:toolbar>
    </x-ui.page-card>

    <x-ui.page-card class="mb-4">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-[13px] font-semibold text-gray-900">Needs Attention</h2>
            <p class="mt-0.5 text-[12px] text-gray-500">Highest-impact open work: shortages and draft purchase orders.</p>
        </div>
        <div id="board-attention" class="divide-y divide-gray-100 p-2">
            <p class="px-2 py-3 text-[13px] text-gray-500">Loading attention items...</p>
        </div>
    </x-ui.page-card>

    <div id="board-pulse" class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5"></div>

    <div id="board-columns" class="flex gap-3 overflow-x-auto pb-2">
        <p class="text-[13px] text-gray-500">Loading board...</p>
    </div>

    <div id="board-action-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="board-action-title">
        <div class="ui-modal-panel max-w-md overflow-hidden">
            <div class="ui-modal-header">
                <h2 id="board-action-title" class="text-[13px] font-semibold text-gray-900">Confirm status change</h2>
                <p id="board-action-message" class="mt-0.5 text-[12px] text-gray-500"></p>
            </div>
            <div class="ui-modal-footer">
                <x-ui.button type="button" variant="secondary" data-close="board-action-modal">Cancel</x-ui.button>
                <x-ui.button type="button" id="board-action-confirm">Confirm</x-ui.button>
            </div>
        </div>
    </div>

    <script>
        window.ordersBoardConfig = @json($boardConfig);
    </script>
@endsection
