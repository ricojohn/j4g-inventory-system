<x-ui.page-card>
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-[13px] font-semibold text-gray-900">History</h2>
        <p class="mt-0.5 text-[12px] text-gray-500">Activity timeline for this order.</p>
    </div>

    <div class="space-y-2 p-4">
        @forelse ($order->activities->sortByDesc('created_at') as $activity)
            <div class="rounded-lg border border-gray-200 px-3 py-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[13px] font-medium text-gray-900">{{ $activity->title }}</p>
                    <p class="text-[11px] text-gray-500">{{ $activity->created_at?->format('M d, Y H:i') }}</p>
                </div>
                @if ($activity->body)
                    <p class="mt-1 text-[12px] text-gray-600">{{ $activity->body }}</p>
                @endif
                <p class="mt-1 text-[11px] text-gray-400">
                    {{ $activity->type }}
                    @if ($activity->actor)
                        · {{ $activity->actor->name }}
                    @endif
                </p>
            </div>
        @empty
            <p class="text-[13px] text-gray-500">No activity yet.</p>
        @endforelse
    </div>
</x-ui.page-card>
