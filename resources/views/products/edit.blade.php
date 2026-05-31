@extends('layouts.app')

@section('page-title', 'Edit Product')

@section('content')
    <x-ui.page-header title="Edit Product" />
    @include('products.partials.form', [
        'action' => route('products.update', $product),
        'method' => 'PUT',
        'product' => $product,
        'categories' => $categories,
        'selectedSizeIds' => $selectedSizeIds,
    ])
@endsection
