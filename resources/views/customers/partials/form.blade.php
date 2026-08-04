@php
    $customer = $customer ?? null;
    $source = old('source', $customer?->source?->value ?? '');
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-ui.label for="name">Name *</x-ui.label>
        <x-ui.input id="name" name="name" type="text" required :value="old('name', $customer?->name)" />
        @error('name')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <x-ui.label for="handle">Handle</x-ui.label>
        <x-ui.input id="handle" name="handle" type="text" :value="old('handle', $customer?->handle)" placeholder="@username" />
        @error('handle')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <x-ui.label for="contact">Contact</x-ui.label>
        <x-ui.input id="contact" name="contact" type="text" :value="old('contact', $customer?->contact)" placeholder="Phone or messenger" />
        @error('contact')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <x-ui.label for="source">Source</x-ui.label>
        <x-ui.select id="source" name="source">
            <option value="">—</option>
            @foreach ($customerSources as $customerSource)
                <option value="{{ $customerSource->value }}" @selected($source === $customerSource->value)>{{ $customerSource->label() }}</option>
            @endforeach
        </x-ui.select>
        @error('source')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <x-ui.label for="notes">Notes</x-ui.label>
        <x-ui.textarea id="notes" name="notes" rows="3">{{ old('notes', $customer?->notes) }}</x-ui.textarea>
        @error('notes')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
