@php
    $supplier = $supplier ?? null;
    $status = old('status', $supplier?->status?->value ?? 'active');
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-ui.label for="name">Name *</x-ui.label>
        <x-ui.input id="name" name="name" type="text" required :value="old('name', $supplier?->name)" />
        @error('name')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <x-ui.label for="contact">Contact</x-ui.label>
        <x-ui.input id="contact" name="contact" type="text" :value="old('contact', $supplier?->contact)" placeholder="Phone, Viber, or messenger handle" />
        <p class="mt-1 text-[11px] text-gray-500">Phone, Viber, or messenger handle</p>
        @error('contact')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <x-ui.label for="address">Address</x-ui.label>
        <x-ui.textarea id="address" name="address" rows="2">{{ old('address', $supplier?->address) }}</x-ui.textarea>
        @error('address')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <x-ui.label for="notes">Notes</x-ui.label>
        <x-ui.textarea id="notes" name="notes" rows="3">{{ old('notes', $supplier?->notes) }}</x-ui.textarea>
        <p class="mt-1 text-[11px] text-gray-500">Lead time, payment terms, etc.</p>
        @error('notes')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <x-ui.label for="status">Status *</x-ui.label>
        <x-ui.select id="status" name="status" required>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </x-ui.select>
        @error('status')<p class="mt-1 text-[12px] text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
