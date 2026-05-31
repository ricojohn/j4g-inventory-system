@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:p-5']) }}>
    @if ($title)
        <div class="mb-4 border-b border-gray-200 pb-3">
            <h2 class="text-lg font-semibold tracking-tight text-gray-900">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
    @endif
    {{ $slot }}
</div>
