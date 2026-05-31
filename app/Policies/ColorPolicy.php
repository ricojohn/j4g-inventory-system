<?php

namespace App\Policies;

use App\Models\Color;
use App\Models\User;

class ColorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage colors');
    }

    public function create(User $user): bool
    {
        return $user->can('manage colors');
    }

    public function update(User $user, Color $color): bool
    {
        return $user->can('manage colors');
    }

    public function delete(User $user, Color $color): bool
    {
        return $user->can('manage colors');
    }
}
