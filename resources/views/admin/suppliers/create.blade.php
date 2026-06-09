@extends('layouts.app')

@section('page-title', 'Add Supplier')

@section('content')
    <x-ui.page-header title="Add Supplier" />

    <x-ui.page-card>
        <form method="POST" action="{{ route('admin.suppliers.store') }}" class="space-y-4 p-4">
            @csrf
            @include('admin.suppliers.partials.form')
            <div class="flex gap-2">
                <x-ui.button type="submit">Save Supplier</x-ui.button>
                <x-ui.button variant="secondary" :href="route('admin.suppliers.index')">Cancel</x-ui.button>
            </div>
        </form>
    </x-ui.page-card>
@endsection
