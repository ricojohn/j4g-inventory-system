<select {{ $attributes->merge([
    'class' => 'h-9 min-h-9 w-full rounded-lg border border-gray-300 px-3 text-[13px] text-gray-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200',
]) }}>
    {{ $slot }}
</select>
