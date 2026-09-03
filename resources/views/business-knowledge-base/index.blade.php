@extends('layouts.app')

@section('title', 'Knowledge Base')

@section('content')
<div class="space-y-4">
    <x-ui.page-header title="Knowledge Base" subtitle="Branch-specific business details and Messenger answer content" />

    <div class="grid gap-4 lg:grid-cols-[1fr_24rem]">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-[14px] font-semibold text-gray-900">Existing entries</h2>
            <div class="space-y-3">
                @forelse ($entries as $entry)
                    <div class="rounded-xl border border-gray-200 p-3">
                        <form method="POST" action="{{ route('business-knowledge-base.update', $entry) }}" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-2 md:grid-cols-2">
                                <x-ui.input name="title" value="{{ $entry->title }}" />
                                <x-ui.input name="category" value="{{ $entry->category }}" />
                            </div>
                            <x-ui.textarea name="content" rows="4" class="mt-2">{{ $entry->content }}</x-ui.textarea>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <x-ui.input type="number" name="sort_order" value="{{ $entry->sort_order }}" class="w-28" />
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="is_active" value="1" @checked($entry->is_active)>
                                    Active
                                </label>
                                <x-ui.button type="submit">Save</x-ui.button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('business-knowledge-base.destroy', $entry) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" class="bg-red-600 hover:bg-red-700">Delete</x-ui.button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No knowledge base entries yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-[14px] font-semibold text-gray-900">Add entry</h2>
            <form method="POST" action="{{ route('business-knowledge-base.store') }}" class="space-y-3">
                @csrf
                <x-ui.input name="title" placeholder="Title" required />
                <x-ui.input name="category" placeholder="Category" value="general" required />
                <x-ui.textarea name="content" rows="8" placeholder="Write the business answer here..." required />
                <x-ui.input type="number" name="sort_order" value="0" min="0" />
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active
                </label>
                <x-ui.button type="submit">Add Entry</x-ui.button>
            </form>
        </div>
    </div>
</div>
@endsection
