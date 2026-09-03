@extends('layouts.app')

@section('title', 'Facebook Pages')

@section('content')
<div class="space-y-4">
    <x-ui.page-header title="Facebook Pages" subtitle="Page credentials are encrypted and never displayed after saving." />
    <x-ui.page-card>
        <h2 class="mb-3 font-semibold">Add Page</h2>
        <form method="POST" action="{{ route('facebook-pages.store') }}" class="grid gap-3 md:grid-cols-2">@csrf
            <x-ui.input name="page_id" placeholder="Meta Page ID" required />
            <x-ui.input name="name" placeholder="Page name" required />
            <x-ui.input name="access_token" type="password" placeholder="Page access token" required />
            <x-ui.input name="graph_api_version" value="{{ config('services.facebook.graph_api_version') }}" required />
            <x-ui.select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></x-ui.select>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ai_enabled" value="1"> Enable AI replies</label>
            <x-ui.select name="automation_user_id"><option value="">Select automation user</option>@foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</x-ui.select>
            <div><x-ui.button type="submit">Save Page</x-ui.button></div>
        </form>
    </x-ui.page-card>
    @foreach ($pages as $page)
        <x-ui.page-card>
            @unless ($page->hasUsableAccessToken())
                <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">The saved Page token is missing or was encrypted with a different APP_KEY. Enter the token again.</div>
            @endunless
            <form method="POST" action="{{ route('facebook-pages.update', $page) }}" class="grid gap-3 md:grid-cols-2">@csrf @method('PUT')
                <x-ui.input name="page_id" value="{{ $page->page_id }}" required />
                <x-ui.input name="name" value="{{ $page->name }}" required />
                <x-ui.input name="access_token" type="password" placeholder="Leave blank to keep saved token" />
                <x-ui.input name="graph_api_version" value="{{ $page->graph_api_version }}" required />
                <x-ui.select name="status"><option value="active" @selected($page->status === 'active')>Active</option><option value="inactive" @selected($page->status === 'inactive')>Inactive</option></x-ui.select>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ai_enabled" value="1" @checked($page->ai_enabled)> Enable AI replies</label>
                <x-ui.select name="automation_user_id"><option value="">Keep current automation user</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected($page->branch->automation_user_id === $user->id)>{{ $user->name }}</option>@endforeach</x-ui.select>
                <div><x-ui.button type="submit">Update Page</x-ui.button></div>
            </form>
        </x-ui.page-card>
    @endforeach
</div>
@endsection
