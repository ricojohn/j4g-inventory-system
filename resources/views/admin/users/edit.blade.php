@extends('layouts.app')

@section('page-title', 'Edit User')

@section('content')
    <x-ui.page-header title="Edit User" />
    @include('admin.users.partials.form', ['user' => $user, 'roles' => $roles])
@endsection
