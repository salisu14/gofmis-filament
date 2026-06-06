<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        $super_admin = User::firstOrCreate(
            ['email' => 'sadmin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password123@'),
                'remember_token' => null,
            ]
        );

        $permissions = Permission::all();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        $super_admin->syncRoles($role);
    }
}
