<x-ui.page-card>
    <div class="border-b border-gray-200 px-4 py-3">
        <h2 class="text-[13px] font-semibold text-gray-900">Layouts</h2>
        <p class="mt-0.5 text-[12px] text-gray-500">Upload versions and approve the final layout before production.</p>
    </div>

    <div class="space-y-4 p-4">
        @if ($canFulfill)
            <form method="POST" action="{{ route('orders.layouts.store', $order) }}" enctype="multipart/form-data" class="grid gap-3 rounded-lg border border-gray-200 p-3 sm:grid-cols-2">
                @csrf
                <div>
                    <x-ui.label for="layout_title">Title *</x-ui.label>
                    <x-ui.input id="layout_title" name="title" type="text" required value="{{ old('title') }}" />
                    @error('title')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.label for="layout_file">File *</x-ui.label>
                    <input
                        id="layout_file"
                        name="layout_file"
                        type="file"
                        required
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.ai,.psd"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-[13px] text-gray-700 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                    />
                    @error('layout_file')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <x-ui.label for="layout_notes">Notes</x-ui.label>
                    <x-ui.textarea id="layout_notes" name="notes" rows="2">{{ old('notes') }}</x-ui.textarea>
                    @error('notes')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-ui.button type="submit">Upload layout</x-ui.button>
                </div>
            </form>
        @endif

        <div class="space-y-2">
            @forelse ($order->layouts->sortByDesc('version') as $layout)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2">
                    <div class="min-w-0">
                        <p class="text-[13px] font-medium text-gray-900">v{{ $layout->version }} — {{ $layout->title }}</p>
                        <p class="mt-0.5 text-[12px] text-gray-500">
                            {{ $layout->status->label() }}
                            @if ($layout->approved_at)
                                · approved {{ $layout->approved_at->format('M d, Y') }}
                                @if ($layout->approver)
                                    by {{ $layout->approver->name }}
                                @endif
                            @endif
                        </p>
                        @if ($layout->notes)
                            <p class="mt-1 text-[12px] text-gray-600">{{ $layout->notes }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($layout->fileUrl())
                            <a href="{{ $layout->fileUrl() }}" target="_blank" rel="noopener noreferrer" class="ui-row-action">Open</a>
                        @endif
                        @if ($canFulfill && $layout->status === \App\Enums\OrderLayoutStatus::Draft)
                            <form method="POST" action="{{ route('orders.layouts.approve', [$order, $layout]) }}" class="inline-flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="approval_channel" value="in_app" />
                                <button type="submit" class="ui-row-action">Approve</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-[13px] text-gray-500">No layouts uploaded.</p>
            @endforelse
        </div>
    </div>
</x-ui.page-card>
