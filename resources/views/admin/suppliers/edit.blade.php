@extends('layouts.app')

@section('page-title', 'Edit Supplier')

@section('content')
    <x-ui.page-header title="Edit Supplier" :subtitle="$supplier->name" />

    <x-ui.page-card>
        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}" class="space-y-4 p-4">
            @csrf
            @method('PUT')
            @include('admin.suppliers.partials.form', ['supplier' => $supplier])
            <div class="flex gap-2">
                <x-ui.button type="submit">Update Supplier</x-ui.button>
                <x-ui.button variant="secondary" :href="route('admin.suppliers.index')">Cancel</x-ui.button>
            </div>
        </form>
    </x-ui.page-card>
@endsection
