@props(['title', 'subtitle' => null, 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div>
        @if ($eyebrow)
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ $eyebrow }}</p>
        @endif
        <h1 @class(['text-xl font-semibold tracking-tight text-gray-900', 'mt-1' => (bool) $eyebrow])>{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
