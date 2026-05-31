@props(['label', 'value', 'description' => null, 'accent' => false, 'href' => null])

@php
    $baseClasses = 'block rounded-xl border border-gray-200 bg-white p-4 shadow-sm';
    $interactiveClasses = $href
        ? 'cursor-pointer transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-900'
        : '';
    $cardClasses = trim($baseClasses.' '.$interactiveClasses);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cardClasses]) }}>
        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
        <p @class([
            'stat-value mt-1 text-xl font-semibold tracking-tight text-gray-900',
            'text-amber-600' => $accent === 'warning',
            'text-red-600' => $accent === 'danger',
        ])>{{ $value }}</p>
        @if ($description)
            <p class="mt-1 text-[11px] text-gray-500">{{ $description }}</p>
        @endif
    </a>
@else
    <div {{ $attributes->merge(['class' => $cardClasses]) }}>
        <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
        <p @class([
            'stat-value mt-1 text-xl font-semibold tracking-tight text-gray-900',
            'text-amber-600' => $accent === 'warning',
            'text-red-600' => $accent === 'danger',
        ])>{{ $value }}</p>
        @if ($description)
            <p class="mt-1 text-[11px] text-gray-500">{{ $description }}</p>
        @endif
    </div>
@endif
