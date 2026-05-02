<?php
// database/seeders/EducationVerifierRoleSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\Permission;

class EducationVerifierRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create the role
        $role = Role::firstOrCreate([
            'uuid' => Str::uuid(),
            'name' => 'education-verifier',
            'guard_name' => 'web',
        ]);

        // Define permissions
        $permissions = [
            'view education verifications',
            'edit education verifications',
            'approve education requests',
            'reject education requests',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'uuid' => Str::uuid(),
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
        }

        // Optional: Assign to an existing user
        // $user = \App\Models\User::where('email', 'verifier@example.com')->first();
        // if ($user) $user->assignRole('education-verifier');
    }
}
