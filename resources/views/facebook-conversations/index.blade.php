@extends('layouts.app')

@section('title', 'Messenger')

@section('content')
<div class="space-y-4">
    <x-ui.page-header title="Facebook Messenger" subtitle="AI and staff-managed customer conversations" />
    <x-ui.page-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-3">Customer PSID</th><th class="p-3">Page</th><th class="p-3">Control</th><th class="p-3">State</th><th class="p-3"></th></tr></thead>
                <tbody>
                @forelse ($conversations as $conversation)
                    <tr class="border-b">
                        <td class="p-3">{{ $conversation->psid }}</td><td class="p-3">{{ $conversation->page->name }}</td>
                        <td class="p-3">{{ ucfirst($conversation->control_mode) }}</td><td class="p-3">{{ str($conversation->state)->headline() }}</td>
                        <td class="p-3 text-right"><a class="text-brand hover:underline" href="{{ route('messenger.show', $conversation) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-gray-500">No Messenger conversations yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $conversations->links() }}</div>
    </x-ui.page-card>
</div>
@endsection
