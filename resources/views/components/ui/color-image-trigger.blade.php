@props([
    'imageUrl' => null,
    'colorName' => '',
    'itemCode' => null,
])

@php
    $subtitle = filled($itemCode) ? "{$itemCode} · {$colorName}" : $colorName;
@endphp

<button
    type="button"
    class="color-image-view-trigger flex items-center gap-2 text-left hover:opacity-80"
    data-image-url="{{ $imageUrl ?? '' }}"
    data-subtitle="{{ $subtitle }}"
    title="View color image"
>
    @if (filled($imageUrl))
        <img src="{{ $imageUrl }}" alt="{{ $colorName }}" class="h-9 w-9 shrink-0 rounded object-cover ring-1 ring-gray-200">
    @else
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-gray-100 text-gray-400" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 9h.008v.008H18V9zm.75 9.75H5.25A2.25 2.25 0 013 16.5V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25z" />
            </svg>
        </span>
    @endif
    <span class="text-gray-700">{{ $colorName }}</span>
</button>
