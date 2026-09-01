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
            <div><x-ui.button type="submit">Save Page</x-ui.button></div>
        </form>
    </x-ui.page-card>
    @foreach ($pages as $page)
        <x-ui.page-card>
            <form method="POST" action="{{ route('facebook-pages.update', $page) }}" class="grid gap-3 md:grid-cols-2">@csrf @method('PUT')
                <x-ui.input name="page_id" value="{{ $page->page_id }}" required />
                <x-ui.input name="name" value="{{ $page->name }}" required />
                <x-ui.input name="access_token" type="password" placeholder="Leave blank to keep saved token" />
                <x-ui.input name="graph_api_version" value="{{ $page->graph_api_version }}" required />
                <x-ui.select name="status"><option value="active" @selected($page->status === 'active')>Active</option><option value="inactive" @selected($page->status === 'inactive')>Inactive</option></x-ui.select>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ai_enabled" value="1" @checked($page->ai_enabled)> Enable AI replies</label>
                <div><x-ui.button type="submit">Update Page</x-ui.button></div>
            </form>
        </x-ui.page-card>
    @endforeach
</div>
@endsection
