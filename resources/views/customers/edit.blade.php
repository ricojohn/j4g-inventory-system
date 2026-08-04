@extends('layouts.app')

@section('page-title', 'Edit Customer')

@section('content')
    <x-ui.page-header
        eyebrow="Customer and team records"
        title="Edit Customer"
        :subtitle="$customer->name"
    />

    <x-ui.page-card>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4 p-4">
            @csrf
            @method('PUT')
            @include('customers.partials.form', ['customer' => $customer])
            <div class="flex gap-2">
                <x-ui.button type="submit">Update Customer</x-ui.button>
                <x-ui.button variant="secondary" :href="route('customers.show', $customer)">Cancel</x-ui.button>
            </div>
        </form>
    </x-ui.page-card>
@endsection
