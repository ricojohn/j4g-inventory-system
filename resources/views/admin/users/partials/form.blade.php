<x-ui.page-card class="mx-auto max-w-xl">
    <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-4 p-4 md:p-5">
        @csrf
        @if ($user)
            @method('PUT')
        @endif

        <div>
            <x-ui.label for="name">Name</x-ui.label>
            <x-ui.input id="name" type="text" name="name" value="{{ old('name', $user?->name) }}" required />
        </div>
        <div>
            <x-ui.label for="email">Email</x-ui.label>
            <x-ui.input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required />
        </div>
        <div>
            <x-ui.label for="password">Password {{ $user ? '(leave blank to keep current)' : '' }}</x-ui.label>
            <x-ui.input id="password" type="password" name="password" :required="! $user" />
        </div>
        <div>
            <x-ui.label for="role">Role</x-ui.label>
            <x-ui.select id="role" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role', $user?->roles->first()?->name) === $role)>{{ $role }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <x-ui.label for="status">Status</x-ui.label>
            <x-ui.select id="status" name="status">
                <option value="active" @selected(old('status', $user?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $user?->status) === 'inactive')>Inactive</option>
            </x-ui.select>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
            <x-ui.button type="submit">Save</x-ui.button>
        </div>
    </form>
</x-ui.page-card>
