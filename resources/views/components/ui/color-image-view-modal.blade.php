<div id="color-image-view-modal" class="ui-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="color-image-view-title">
    <div class="ui-modal-panel max-w-md overflow-hidden">
        <div class="ui-modal-header">
            <h2 id="color-image-view-title" class="text-[13px] font-semibold text-gray-900">Color Image</h2>
            <p id="color-image-view-subtitle" class="mt-0.5 text-[13px] text-gray-500"></p>
        </div>
        <div class="ui-modal-body">
            <div class="flex items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
                <img id="color-image-view-preview" src="" alt="Color preview" class="hidden max-h-64 w-auto rounded object-contain">
                <p id="color-image-view-empty" class="py-10 text-[13px] text-gray-500">No image uploaded for this color.</p>
            </div>
        </div>
        <div class="ui-modal-footer">
            <x-ui.button type="button" variant="secondary" data-close="color-image-view-modal">Close</x-ui.button>
        </div>
    </div>
</div>
