<?php

namespace App\Policies;

use App\Models\Size;
use App\Models\User;

class SizePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage sizes');
    }

    public function view(User $user, Size $size): bool
    {
        return $user->can('manage sizes');
    }

    public function create(User $user): bool
    {
        return $user->can('manage sizes');
    }

    public function update(User $user, Size $size): bool
    {
        return $user->can('manage sizes');
    }

    public function delete(User $user, Size $size): bool
    {
        return $user->can('manage sizes');
    }
}
