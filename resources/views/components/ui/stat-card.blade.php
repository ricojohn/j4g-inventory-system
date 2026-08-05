@props(['label', 'value', 'description' => null, 'accent' => false, 'href' => null, 'icon' => null])

@php
    $baseClasses = 'block rounded-xl border border-gray-200 bg-white p-4 shadow-sm';
    $interactiveClasses = $href
        ? 'cursor-pointer transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand'
        : '';
    $cardClasses = trim($baseClasses.' '.$interactiveClasses);

    $iconBg = match ($accent) {
        'warning' => 'bg-amber-50 text-amber-600',
        'danger' => 'bg-red-50 text-red-600',
        'info' => 'bg-brand-soft text-brand',
        'purple' => 'bg-purple-50 text-purple-600',
        default => 'bg-brand-soft text-brand',
    };

    $icons = [
        'products' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
        'stock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />',
        'reserved' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />',
        'available' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'low-stock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
        'out-of-stock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />',
        'orders' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />',
        'pos' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cardClasses]) }}>
        <div class="flex items-start gap-3">
            @if ($icon && isset($icons[$icon]))
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $iconBg }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                </div>
            @endif
            <div class="min-w-0 flex-1">
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
        </div>
    </a>
@else
    <div {{ $attributes->merge(['class' => $cardClasses]) }}>
        <div class="flex items-start gap-3">
            @if ($icon && isset($icons[$icon]))
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $iconBg }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                </div>
            @endif
            <div class="min-w-0 flex-1">
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
        </div>
    </div>
@endif
