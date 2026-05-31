@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'px-4 py-8 text-center']) }}>
    <h3 class="text-[13px] font-semibold text-gray-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 text-[13px] text-gray-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex justify-center">
            {{ $slot }}
        </div>
    @endif
</div>
