@extends('layouts.app')

@section('page-title', 'Create User')

@section('content')
    <x-ui.page-header title="Create User" />
    @include('admin.users.partials.form', ['user' => null, 'roles' => $roles])
@endsection
