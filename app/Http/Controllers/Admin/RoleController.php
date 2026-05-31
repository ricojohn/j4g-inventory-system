<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TableDataRequest;
use App\Support\PaginatedJsonResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('manage roles'), 403);

        return view('admin.roles.index');
    }

    public function data(TableDataRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage roles'), 403);

        $roles = Role::query()
            ->withCount('permissions')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($request->perPageCount(), ['*'], 'page', $request->pageNumber());

        return PaginatedJsonResponse::fromPaginator(
            $roles->through(fn (Role $role) => $this->formatRole($role))
        );
    }

    public function edit(Role $role): View
    {
        abort_unless(auth()->user()?->can('manage roles'), 403);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(auth()->user()?->can('manage roles'), 403);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role permissions updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permission_count' => $role->permissions_count,
            'edit_url' => route('admin.roles.edit', $role),
        ];
    }
}
