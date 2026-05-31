@extends('layouts.app')

@section('page-title', 'Create Product')

@section('content')
    <x-ui.page-header title="Create Product" />
    @include('products.partials.form', [
        'action' => route('products.store'),
        'method' => 'POST',
        'product' => null,
        'categories' => $categories,
        'selectedSizeIds' => [],
    ])
@endsection
