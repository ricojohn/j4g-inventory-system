@props(['status' => 'active'])

@php
    $normalized = strtolower((string) $status);
    $classes = match ($normalized) {
        'active' => 'bg-green-100 text-green-800',
        'inactive' => 'bg-gray-100 text-gray-700',
        default => 'bg-gray-100 text-gray-700',
    };
    $label = ucfirst($normalized);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {$classes}"]) }}>
    {{ $label }}
</span>
