@extends('layouts.app')

@section('page-title', 'Production')

@push('scripts')
    @vite(['resources/js/production-board.js'])
@endpush

@section('content')
    <x-ui.page-header
        eyebrow="Floor operations"
        title="Work in progress"
        subtitle="Track jobs across printing, sewing, QC, and packing stages."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('orders.board')">Orders board</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.page-card class="mb-4">
        <x-slot:toolbar>
            <div class="ui-toolbar-form">
                <x-ui.input type="search" id="production-search" placeholder="Search production..." class="w-auto! min-w-48" />
            </div>
        </x-slot:toolbar>
    </x-ui.page-card>

    <div id="production-columns" class="flex gap-3 overflow-x-auto pb-2">
        <p class="text-[13px] text-gray-500">Loading production board...</p>
    </div>

    <script>
        window.productionBoardConfig = @json($boardConfig);
    </script>
@endsection
