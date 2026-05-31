<textarea {{ $attributes->merge([
    'class' => 'w-full rounded-lg border border-gray-300 px-3 py-2 text-[13px] text-gray-900 placeholder:text-gray-400 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200',
]) }}>{{ $slot }}</textarea>
