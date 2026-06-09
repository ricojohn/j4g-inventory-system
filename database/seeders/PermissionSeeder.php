<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view inventory',
            'stock in',
            'stock out',
            'reserve stock',
            'release stock',
            'damage stock',
            'adjust stock',
            'view stock history',
            'view low stock report',
            'view out of stock report',
            'manage users',
            'manage roles',
            'manage permissions',
            'manage sizes',
            'manage colors',
            'manage suppliers',
            'view orders',
            'create orders',
            'fulfill orders',
            'cancel orders',
            'view supplier orders',
            'create supplier orders',
            'receive supplier orders',
            'cancel supplier orders',
            'manage integrations',
            'use ai assistant',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('Admin');
        $manager = Role::findOrCreate('Manager');
        $staff = Role::findOrCreate('Staff');
        $viewer = Role::findOrCreate('Viewer');

        $admin->syncPermissions(Permission::all());

        $managerPermissions = Permission::whereNotIn('name', [
            'manage roles',
            'manage permissions',
        ])->get();
        $manager->syncPermissions($managerPermissions);

        $staffPermissions = Permission::whereIn('name', [
            'view dashboard',
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view inventory',
            'stock in',
            'stock out',
            'reserve stock',
            'release stock',
            'damage stock',
            'adjust stock',
            'view stock history',
            'view low stock report',
            'view out of stock report',
            'view orders',
            'create orders',
            'fulfill orders',
            'view supplier orders',
            'create supplier orders',
            'receive supplier orders',
            'use ai assistant',
        ])->get();
        $staff->syncPermissions($staffPermissions);

        $viewerPermissions = Permission::whereIn('name', [
            'view dashboard',
            'view products',
            'view inventory',
            'view stock history',
            'view low stock report',
            'view out of stock report',
            'view orders',
            'view supplier orders',
        ])->get();
        $viewer->syncPermissions($viewerPermissions);
    }
}
