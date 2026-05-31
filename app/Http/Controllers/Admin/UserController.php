<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TableDataRequest;
use App\Models\User;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        return view('admin.users.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage users'), 403);

        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $users->through(fn (User $user) => $this->formatUser($user))
        );
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        return view('admin.users.create', [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->can('manage users'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->join(', '),
            'status' => $user->status,
            'edit_url' => route('admin.users.edit', $user),
        ];
    }
}
