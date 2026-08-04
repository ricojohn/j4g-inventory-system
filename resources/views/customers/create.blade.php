@extends('layouts.app')

@section('page-title', 'Add Customer')

@section('content')
    <x-ui.page-header
        eyebrow="Customer and team records"
        title="Add Customer"
        subtitle="Create a reusable profile for orders and follow-ups."
    />

    <x-ui.page-card>
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-4 p-4">
            @csrf
            @include('customers.partials.form')
            <div class="flex gap-2">
                <x-ui.button type="submit">Save Customer</x-ui.button>
                <x-ui.button variant="secondary" :href="route('customers.index')">Cancel</x-ui.button>
            </div>
        </form>
    </x-ui.page-card>
@endsection
