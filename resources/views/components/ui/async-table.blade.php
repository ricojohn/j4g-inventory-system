@props([
    'tbodyId' => 'async-table-body',
    'paginationId' => 'table-pagination',
    'loadingId' => 'table-loading',
    'errorId' => 'table-error',
])

<table {{ $attributes->merge(['class' => 'ui-table']) }}>
    <thead>
        {{ $head }}
    </thead>
    <tbody id="{{ $tbodyId }}"></tbody>
</table>

<div id="{{ $loadingId }}" class="hidden mt-3 text-sm text-gray-500">Loading...</div>
<div id="{{ $errorId }}" class="hidden mt-3 text-sm text-red-600"></div>
<div id="{{ $paginationId }}" class="mt-4 hidden"></div>
