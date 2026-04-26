<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Permission;
use App\Models\Role;

class ImprestPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Transactions
            'imprest.transactions.view',
            'imprest.transactions.create',
            'imprest.transactions.edit',
            'imprest.transactions.approve',
            'imprest.transactions.void',

            // Funds
            'imprest.funds.view',
            'imprest.funds.create',
            'imprest.funds.edit',
            'imprest.funds.reconcile',
            'imprest.funds.replenish',

            // Global
            'imprest.manage_all',
            'imprest.bypass_custodian_check',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['uuid' => Str::uuid()->toString()]
            );
        }

        // Create roles with explicit UUIDs
        $roleData = [
            ['name' => 'super_admin', 'guard_name' => 'web', 'uuid' => Str::uuid()->toString()],
            ['name' => 'admin', 'guard_name' => 'web', 'uuid' => Str::uuid()->toString()],
            ['name' => 'custodian', 'guard_name' => 'web', 'uuid' => Str::uuid()->toString()],
            ['name' => 'auditor', 'guard_name' => 'web', 'uuid' => Str::uuid()->toString()],
        ];

        foreach ($roleData as $data) {
            Role::firstOrCreate(
                ['name' => $data['name'], 'guard_name' => $data['guard_name']],
                ['uuid' => $data['uuid']]
            );
        }

        // Fetch created roles
        $superAdmin = Role::findByName('super_admin', 'web');
        $admin = Role::findByName('admin', 'web');
        $custodian = Role::findByName('custodian', 'web');
        $auditor = Role::findByName('auditor', 'web');

        // Assign all permissions to super_admin
        $superAdmin->syncPermissions(Permission::all());

        // Assign admin permissions
        $admin->syncPermissions([
            'imprest.transactions.view',
            'imprest.transactions.create',
            'imprest.transactions.edit',
            'imprest.transactions.approve',
            'imprest.transactions.void',
            'imprest.funds.view',
            'imprest.funds.create',
            'imprest.funds.edit',
            'imprest.funds.reconcile',
            'imprest.funds.replenish',
            'imprest.manage_all',
            'imprest.bypass_custodian_check',
        ]);

        // Assign custodian permissions
        $custodian->syncPermissions([
            'imprest.transactions.view',
            'imprest.transactions.create',
            'imprest.funds.view',
        ]);

        // Assign auditor permissions
        $auditor->syncPermissions([
            'imprest.transactions.view',
            'imprest.funds.view',
            'imprest.funds.reconcile',
        ]);
    }
}
