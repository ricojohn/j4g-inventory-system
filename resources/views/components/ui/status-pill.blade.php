@props(['status' => 'active', 'dot' => true])

@php
    $normalized = strtolower((string) $status);
    $classes = match ($normalized) {
        'active', 'ready', 'healthy', 'cleared', 'paid', 'approved', 'fulfilled', 'completed' => 'bg-green-100 text-green-800',
        'inactive', 'superseded', 'cancelled' => 'bg-gray-100 text-gray-700',
        'pending', 'awaiting dp', 'awaiting_dp', 'awaiting approval', 'partial', 'partially reserved', 'partially_reserved', 'low stock', 'low_stock' => 'bg-amber-100 text-amber-800',
        'reserved', 'confirmed', 'in production', 'in_production', 'printing', 'follow-up', 'follow_up' => 'bg-blue-100 text-blue-800',
        'partial dp', 'partial_dp', 'finance', 'receivable' => 'bg-purple-100 text-purple-800',
        'overdue', 'blocked', 'out of stock', 'out_of_stock', 'unpaid', 'danger' => 'bg-red-100 text-red-800',
        'quality check', 'quality_check', 'po partial', 'po_partial' => 'bg-orange-100 text-orange-800',
        default => 'bg-gray-100 text-gray-700',
    };
    $dotClasses = match ($normalized) {
        'active', 'ready', 'healthy', 'cleared', 'paid', 'approved', 'fulfilled', 'completed' => 'bg-green-600',
        'inactive', 'superseded', 'cancelled' => 'bg-gray-500',
        'pending', 'awaiting dp', 'awaiting_dp', 'awaiting approval', 'partial', 'partially reserved', 'partially_reserved', 'low stock', 'low_stock' => 'bg-amber-600',
        'reserved', 'confirmed', 'in production', 'in_production', 'printing', 'follow-up', 'follow_up' => 'bg-blue-600',
        'partial dp', 'partial_dp', 'finance', 'receivable' => 'bg-purple-600',
        'overdue', 'blocked', 'out of stock', 'out_of_stock', 'unpaid', 'danger' => 'bg-red-600',
        'quality check', 'quality_check', 'po partial', 'po_partial' => 'bg-orange-600',
        default => 'bg-gray-500',
    };
    $label = str_replace('_', ' ', ucfirst($normalized));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium {$classes}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
    @endif
    {{ $slot->isEmpty() ? $label : $slot }}
</span>
