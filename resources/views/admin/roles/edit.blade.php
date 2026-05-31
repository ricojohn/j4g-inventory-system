@extends('layouts.app')

@section('page-title', 'Edit Role')

@section('content')
    <x-ui.page-header :title="'Edit Role: ' . $role->name" />

    <x-ui.page-card class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-4 p-4 md:p-5">
            @csrf
            @method('PUT')

            <div class="grid gap-2 md:grid-cols-2">
                @foreach ($permissions as $permission)
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked($role->permissions->contains('name', $permission->name))>
                        {{ $permission->name }}
                    </label>
                @endforeach
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="secondary" :href="route('admin.roles.index')">Cancel</x-ui.button>
                <x-ui.button type="submit">Save Permissions</x-ui.button>
            </div>
        </form>
    </x-ui.page-card>
@endsection
