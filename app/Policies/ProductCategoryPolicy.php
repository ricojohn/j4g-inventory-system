<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view categories');
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('view categories');
    }

    public function create(User $user): bool
    {
        return $user->can('create categories');
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('edit categories');
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->can('delete categories');
    }
}
