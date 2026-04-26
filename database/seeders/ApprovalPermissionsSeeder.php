<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ApprovalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view_approval_flows',
            'submit_widow_loans',
            'approve_widow_loans',
            'reject_widow_loans',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Assign to roles (assuming roles exist from other seeders)
        $director = Role::findOrCreate('director');
        $director->givePermissionTo(['view_approval_flows', 'approve_widow_loans', 'reject_widow_loans']);

        $financeManager = Role::findOrCreate('finance_manager');
        $financeManager->givePermissionTo(['view_approval_flows', 'approve_widow_loans', 'reject_widow_loans']);

        $loanOfficer = Role::findOrCreate('loan_officer');
        $loanOfficer->givePermissionTo(['view_approval_flows', 'submit_widow_loans']);
        
        $admin = Role::findOrCreate('admin');
        $admin->givePermissionTo($permissions);
    }
}
