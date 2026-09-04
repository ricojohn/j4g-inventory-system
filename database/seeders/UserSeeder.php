<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'MAIN')->value('id');
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@j4g.test', 'role' => 'Admin'],
            ['name' => 'Manager User', 'email' => 'manager@j4g.test', 'role' => 'Manager'],
            ['name' => 'Staff User', 'email' => 'staff@j4g.test', 'role' => 'Staff'],
            ['name' => 'Viewer User', 'email' => 'viewer@j4g.test', 'role' => 'Viewer'],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'branch_id' => $branchId,
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
