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
        $users = [
            [
                'id'             => 1,
                'name'           => 'Super Admin',
                'email'          => 'sadmin@admin.com',
                'password'       => bcrypt('password123@'),
                'remember_token' => null,
            ],
        ];

        User::insert($users);
        $super_admin = User::first();
        $permissions = Permission::all();
        $role = Role::first();
        $role = $role->givePermissionTo($permissions);
        $super_admin->syncRoles($role);
    }
}
