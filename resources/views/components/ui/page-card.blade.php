<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @isset($toolbar)
        <div class="flex items-center gap-2 border-b border-gray-200 px-3 py-2">
            {{ $toolbar }}
        </div>
    @endisset
    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
</div>
