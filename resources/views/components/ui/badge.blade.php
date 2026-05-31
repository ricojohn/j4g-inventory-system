@props(['status' => 'OK'])

@php
    $statusValue = strtoupper((string) $status);
    $classes = match ($statusValue) {
        'OUT OF STOCK', 'OUT_OF_STOCK' => 'bg-red-100 text-red-800',
        'LOW STOCK', 'LOW_STOCK' => 'bg-amber-100 text-amber-800',
        'RESERVED' => 'bg-blue-100 text-blue-800',
        'DAMAGED' => 'bg-gray-200 text-gray-800',
        default => 'bg-green-100 text-green-800',
    };
    $label = match ($statusValue) {
        'OUT_OF_STOCK' => 'OUT OF STOCK',
        'LOW_STOCK' => 'LOW STOCK',
        'OK' => 'OK',
        default => str_replace('_', ' ', $statusValue),
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {$classes}"]) }}>
    {{ $label }}
</span>
