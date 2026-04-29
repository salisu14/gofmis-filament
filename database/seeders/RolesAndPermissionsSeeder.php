<?php
// database/seeders/RolesAndPermissionsSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view_deceased',
            'create_deceased',
            'edit_deceased',
            'delete_deceased',
            'view_orphans',
            'create_orphans',
            'edit_orphans',
            'view_widows',
            'create_widows',
            'edit_widows',
            'request_loans',
            'request_education',
            'request_healthcare',
            'request_welfare',
            'view_reports',
            'manage_users',
            'manage_zones',
            'approve_requests',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $coordinator = Role::create(['name' => 'coordinator']);
        $coordinator->givePermissionTo([
            'view_deceased', 'create_deceased', 'edit_deceased',
            'view_orphans', 'create_orphans', 'edit_orphans',
            'view_widows', 'create_widows', 'edit_widows',
            'request_loans', 'request_education', 'request_healthcare', 'request_welfare',
            'view_reports',
        ]);

        $staff = Role::create(['name' => 'staff']);
        $staff->givePermissionTo([
            'view_deceased', 'view_orphans', 'view_widows',
            'view_reports',
        ]);

        // Create super admin
        Role::create(['name' => 'super-admin']);
    }
}
