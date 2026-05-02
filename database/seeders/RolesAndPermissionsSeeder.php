<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        // =================================================================
        // 1. Define Permissions
        // =================================================================

        $permissions = [
            // --- System & Admin ---
            'view_users', 'create_users', 'edit_users', 'delete_users', 'assign_roles',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            'view_settings', 'edit_settings',
            'admin_dashboard_access',

            // --- Beneficiaries ---
            'view_deceased', 'create_deceased', 'edit_deceased', 'delete_deceased',
            'view_orphans', 'create_orphans', 'edit_orphans', 'delete_orphans',
            'view_widows', 'create_widows', 'edit_widows', 'delete_widows',
            'mark_orphan_married', 'mark_orphan_unmarried',

            // --- Zones & Locations ---
            'view_zones', 'create_zones', 'edit_zones', 'delete_zones',

            // --- Projects ---
            'view_projects', 'create_projects', 'edit_projects', 'delete_projects', 'manage_projects',

            // --- Interventions (Education) ---
            'view_education_interventions', 'create_education_interventions', 'edit_education_interventions', 'delete_education_interventions', 'verify_education_interventions',

            // --- Interventions (Healthcare) ---
            'view_healthcare_interventions', 'create_healthcare_interventions', 'edit_healthcare_interventions', 'delete_healthcare_interventions', 'approve_healthcare_interventions',

            // --- Interventions (Welfare) ---
            'view_welfare_interventions', 'create_welfare_interventions', 'edit_welfare_interventions', 'delete_welfare_interventions', 'approve_welfare_interventions',

            // --- Loans ---
            'view_loans', 'create_loans', 'edit_loans', 'delete_loans', 'approve_loans', 'reject_loans', 'disburse_loans',
            'view_repayments', 'create_repayments', 'edit_repayments', 'delete_repayments',

            // --- Imprest ---
            'imprest_view_transactions', 'imprest_create_transactions', 'imprest_edit_transactions', 'imprest_delete_transactions', 'imprest_approve_transactions', 'imprest_void_transactions',
            'imprest_view_funds', 'imprest_reconcile_funds', 'imprest_replenish_funds',

            // --- Reports ---
            'view_reports', 'export_reports',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        // =================================================================
        // 2. Define Roles & Assign Permissions
        // =================================================================

        // --------------------------------------------------------------
        // Super Admin: Full Access
        // --------------------------------------------------------------
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => $guard]);
        $superAdmin->syncPermissions(Permission::all());

        // --------------------------------------------------------------
        // Admin: Full Operational Access (No System Settings/User mgmt)
        // --------------------------------------------------------------
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $admin->syncPermissions([
            // Beneficiaries
            'view_deceased', 'create_deceased', 'edit_deceased', 'delete_deceased',
            'view_orphans', 'create_orphans', 'edit_orphans', 'delete_orphans',
            'view_widows', 'create_widows', 'edit_widows', 'delete_widows',
            'mark_orphan_married', 'mark_orphan_unmarried',

            // Zones & Projects
            'view_zones', 'create_zones', 'edit_zones', 'delete_zones',
            'view_projects', 'create_projects', 'edit_projects', 'delete_projects', 'manage_projects',

            // Interventions
            'view_education_interventions', 'create_education_interventions', 'edit_education_interventions', 'delete_education_interventions', 'verify_education_interventions',
            'view_healthcare_interventions', 'create_healthcare_interventions', 'edit_healthcare_interventions', 'delete_healthcare_interventions', 'approve_healthcare_interventions',
            'view_welfare_interventions', 'create_welfare_interventions', 'edit_welfare_interventions', 'delete_welfare_interventions', 'approve_welfare_interventions',

            // Loans
            'view_loans', 'create_loans', 'edit_loans', 'delete_loans', 'approve_loans', 'reject_loans', 'disburse_loans',
            'view_repayments', 'create_repayments', 'edit_repayments', 'delete_repayments',

            // Imprest
            'imprest_view_transactions', 'imprest_create_transactions', 'imprest_edit_transactions', 'imprest_delete_transactions', 'imprest_approve_transactions', 'imprest_void_transactions',
            'imprest_view_funds', 'imprest_reconcile_funds', 'imprest_replenish_funds',

            // Reports
            'view_reports', 'export_reports',
        ]);

        // --------------------------------------------------------------
        // Coordinator: Field Staff (View/Create only, No Approvals)
        // --------------------------------------------------------------
        $coordinator = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => $guard]);
        $coordinator->syncPermissions([
            // Beneficiaries
            'view_deceased', 'create_deceased', 'edit_deceased',
            'view_orphans', 'create_orphans', 'edit_orphans',
            'view_widows', 'create_widows', 'edit_widows',

            // Zones & Projects
            'view_zones',
            'view_projects',

            // Interventions (Requests)
            'create_education_interventions',
            'create_healthcare_interventions',
            'create_welfare_interventions',

            // Loans (Requests)
            'create_loans', 'view_loans',

            // Reports
            'view_reports',
        ]);

        // --------------------------------------------------------------
        // Education Verifier: Specific to Education Verification
        // --------------------------------------------------------------
        $educationVerifier = Role::firstOrCreate(['name' => 'education-verifier', 'guard_name' => $guard]);
        $educationVerifier->syncPermissions([
            'view_education_interventions',
            'verify_education_interventions',
            'view_orphans', 'view_widows', // Needed to see who is receiving education
            'view_reports',
        ]);

        // --------------------------------------------------------------
        // Finance Custodian: Manages Imprest and Loan Disbursements
        // --------------------------------------------------------------
        $financeCustodian = Role::firstOrCreate(['name' => 'finance-custodian', 'guard_name' => $guard]);
        $financeCustodian->syncPermissions([
            // Imprest
            'imprest_view_transactions', 'imprest_create_transactions', 'imprest_edit_transactions',
            'imprest_view_funds',

            // Loans
            'view_loans', 'disburse_loans',
            'view_repayments',
        ]);

        // --------------------------------------------------------------
        // Auditor: Read-only access to financial data
        // --------------------------------------------------------------
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => $guard]);
        $auditor->syncPermissions([
            'view_projects',
            'imprest_view_transactions', 'imprest_view_funds', 'imprest_reconcile_funds',
            'view_loans', 'view_repayments',
            'view_reports', 'export_reports',
        ]);
    }
}
