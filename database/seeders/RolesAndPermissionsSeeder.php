<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->reconcile(false);
    }

    public function runPreservingExistingPermissions(): void
    {
        $this->reconcile(true);
    }

    private function reconcile(bool $preserveExistingPermissions): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        // =================================================================
        // 1. Define All Active, Legacy & Specialized Permissions
        // =================================================================

        $permissions = [
            // --- System & Security Admin ---
            'view_users', 'create_users', 'edit_users', 'delete_users', 'assign_roles',
            'view_roles', 'create_roles', 'edit_roles', 'delete_roles',
            'view_permissions', 'create_permissions', 'edit_permissions', 'delete_permissions',
            'view_settings', 'edit_settings', 'manage_settings',
            'admin_dashboard_access',
            'view_finances', 'view_interventions', 'view_sponsorships', 'view_addresses',
            'view_company_information', 'edit_company_information',
            'user_management_access', 'notification_access',
            // Legacy / Standalone User/Role Aliases
            'user_access', 'user_create', 'user_edit', 'user_delete',
            'role_access', 'role_edit',

            // --- Beneficiaries ---
            'view_deceased', 'create_deceased', 'edit_deceased', 'delete_deceased', 'import_deceased', 'export_deceased',
            'view_orphans', 'create_orphans', 'edit_orphans', 'delete_orphans', 'import_orphans', 'export_orphans',
            'view_widows', 'create_widows', 'edit_widows', 'delete_widows', 'import_widows', 'export_widows',
            'mark_orphan_married', 'mark_orphan_unmarried',

            // --- Zones & Locations ---
            'view_zones', 'create_zones', 'edit_zones', 'delete_zones',

            // --- Projects & Sponsorships ---
            'view_projects', 'create_projects', 'edit_projects', 'delete_projects', 'manage_projects',
            'create_sponsorships', 'edit_sponsorships', 'delete_sponsorships',

            // --- Interventions (Education) ---
            'view_education_interventions', 'create_education_interventions', 'edit_education_interventions', 'delete_education_interventions', 'verify_education_interventions', 'approve_education_interventions', 'reject_education_interventions',
            'view education verifications', 'edit education verifications', 'approve education requests', 'reject education requests',

            // --- Interventions (Healthcare) ---
            'view_healthcare_interventions', 'create_healthcare_interventions', 'edit_healthcare_interventions', 'delete_healthcare_interventions', 'approve_healthcare_interventions', 'treat_healthcare_requests',

            // --- Interventions (Welfare) ---
            'view_welfare_interventions', 'create_welfare_interventions', 'edit_welfare_interventions', 'delete_welfare_interventions', 'approve_welfare_interventions',

            // --- Loans & Disbursal ---
            'view_loans', 'create_loans', 'edit_loans', 'delete_loans', 'approve_loans', 'reject_loans', 'disburse_loans',
            'disburse_widow_loans', 'collect_widow_loans',
            'view_repayments', 'create_repayments', 'edit_repayments', 'delete_repayments',

            // --- Active Dotted Imprest Permissions ---
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

            // --- Legacy Underscored Imprest Permissions (Compatibility) ---
            'imprest_view_transactions',
            'imprest_create_transactions',
            'imprest_edit_transactions',
            'imprest_delete_transactions',
            'imprest_approve_transactions',
            'imprest_void_transactions',
            'imprest_view_funds',
            'imprest_reconcile_funds',
            'imprest_replenish_funds',

            // --- Approval Flows & Widow Loan Approvals ---
            'view_approval_flows',
            'submit_widow_loans',
            'approve_widow_loans',
            'reject_widow_loans',

            // --- ID Cards ---
            'view_id_cards',
            'create_id_cards',
            'id_cards.create',
            'id_cards.bulk_print',

            // --- Orphan Education Analytics & Progression ---
            'orphan_education.analytics.view',
            'orphan_education.analytics.export',
            'orphan_education.override_academic_progression',

            // --- Biometrics ---
            'biometrics.view',
            'biometrics.enroll',
            'biometrics.revoke',
            'biometrics.verify',
            'biometrics.identify',
            'biometrics.override',

            // --- Finance Consolidated Report ---
            'finance.consolidated_report.view',
            'finance.consolidated_report.export',

            // --- Out of Pocket Expenditure ---
            'out_of_pocket_expenditure.view',
            'out_of_pocket_expenditure.create',
            'out_of_pocket_expenditure.approve',

            // --- Reports & Medical ---
            'view_reports', 'export_reports', 'view_medicals',
        ];

        // Create permissions idempotently
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        // Compatibility entry points may add canonical grants, but must not
        // detach local/custom assignments or grant unrelated permission records.
        $assign = function (Role $role, array $names) use ($preserveExistingPermissions): void {
            if ($preserveExistingPermissions) {
                $role->givePermissionTo($names);
            } else {
                $role->syncPermissions($names);
            }
        };

        // =================================================================
        // 2. Define Roles & Assign Permissions
        // =================================================================

        // --------------------------------------------------------------
        // Super Admin: Full Access
        // --------------------------------------------------------------
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        $assign($superAdmin, $permissions);

        // --------------------------------------------------------------
        // Admin: Operational Access and Hierarchy-Limited User Administration
        // --------------------------------------------------------------
        $adminPermissions = array_diff($permissions, [
            'view_settings', 'edit_settings',
            // Admin may manage ordinary users, but never define role privileges.
            'create_roles', 'edit_roles', 'delete_roles', 'role_edit',
            'create_permissions', 'edit_permissions', 'delete_permissions',
            // Operational fund permissions suffice; custodian bypass is privileged.
            'imprest.manage_all', 'imprest.bypass_custodian_check',
            // No current workflow establishes an operational-admin need for overrides.
            'biometrics.override', 'orphan_education.override_academic_progression',
        ]);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $assign($admin, $adminPermissions);

        // --------------------------------------------------------------
        // Coordinator: Field Staff (View/Create Field Data, No Approvals/Finance)
        // --------------------------------------------------------------
        $coordinator = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => $guard]);
        $assign($coordinator, [
            // Beneficiaries
            'view_deceased', 'create_deceased', 'edit_deceased',
            'view_orphans', 'create_orphans', 'edit_orphans',
            'view_widows', 'create_widows', 'edit_widows',

            // Zones & Projects
            'view_zones',
            'view_projects', 'create_projects', 'edit_projects',

            // Interventions (Requests)
            'create_education_interventions',
            'create_welfare_interventions',

            // Loans (Requests)
            'create_loans', 'view_loans', 'edit_loans',

            // Biometrics (Field Ops); ID-card downloads use the controller zone check.
            'biometrics.view', 'biometrics.enroll',

            // Reports
            'view_reports',
        ]);

        // --------------------------------------------------------------
        // Education Verifier: Specific to Education Verification
        // --------------------------------------------------------------
        $educationVerifierNames = ['education-verifier', 'education_verifier'];
        foreach ($educationVerifierNames as $roleName) {
            $eduRole = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $assign($eduRole, [
                'view_education_interventions',
                'verify_education_interventions',
                'view education verifications',
                'edit education verifications',
                'approve education requests',
                'reject education requests',
                'view_orphans', 'view_widows',
                'view_reports',
            ]);
        }

        // --------------------------------------------------------------
        // Custodian / Finance Custodian: Manages Imprest & Loan Disbursal
        // --------------------------------------------------------------
        $custodianNames = ['custodian', 'finance-custodian'];
        foreach ($custodianNames as $roleName) {
            $custodianRole = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $assign($custodianRole, [
                // Active Dotted Imprest
                'imprest.transactions.view', 'imprest.transactions.create', 'imprest.transactions.edit',
                'imprest.funds.view',

                // Legacy Imprest
                'imprest_view_transactions', 'imprest_create_transactions', 'imprest_edit_transactions',
                'imprest_view_funds',

                // Loans & Disbursal
                'view_loans', 'disburse_loans', 'disburse_widow_loans',
                'view_repayments',
            ]);
        }

        // --------------------------------------------------------------
        // Auditor: Read-Only Access to Financial & Analytical Data
        // --------------------------------------------------------------
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => $guard]);
        $assign($auditor, [
            'view_projects',
            // Imprest Active & Legacy
            'imprest.transactions.view', 'imprest.funds.view',
            'imprest_view_transactions', 'imprest_view_funds',

            // Loans & Financial Reports
            'view_loans', 'view_repayments',
            'view_reports', 'export_reports',

            // Specialized Read-Only
            'view_id_cards',
            'orphan_education.analytics.view', 'orphan_education.analytics.export',
            'biometrics.view',
            'finance.consolidated_report.view', 'finance.consolidated_report.export',
            'out_of_pocket_expenditure.view',
        ]);

        // --------------------------------------------------------------
        // Director: Approval Flows & Executive Reporting
        // --------------------------------------------------------------
        $director = Role::firstOrCreate(['name' => 'director', 'guard_name' => $guard]);
        $assign($director, [
            'view_approval_flows', 'approve_widow_loans', 'reject_widow_loans',
            'view_reports', 'export_reports',
            'finance.consolidated_report.view',
        ]);

        // --------------------------------------------------------------
        // Finance Manager: Approval Flows, Finance Reports & Out-of-Pocket
        // --------------------------------------------------------------
        $financeManager = Role::firstOrCreate(['name' => 'finance_manager', 'guard_name' => $guard]);
        $assign($financeManager, [
            'view_approval_flows', 'approve_widow_loans', 'reject_widow_loans',
            'finance.consolidated_report.view', 'finance.consolidated_report.export',
            'out_of_pocket_expenditure.view', 'out_of_pocket_expenditure.approve',
        ]);

        // --------------------------------------------------------------
        // Loan Officer: Loan Workflow & Submission
        // --------------------------------------------------------------
        $loanOfficer = Role::firstOrCreate(['name' => 'loan_officer', 'guard_name' => $guard]);
        $assign($loanOfficer, [
            'view_approval_flows', 'submit_widow_loans',
            'view_loans', 'create_loans', 'edit_loans',
        ]);

        // --------------------------------------------------------------
        // Demo Observer: Read-Only Access
        // --------------------------------------------------------------
        $demoObserver = Role::firstOrCreate(['name' => 'demo_observer', 'guard_name' => $guard]);
        $assign($demoObserver, [
            'view_deceased', 'view_orphans', 'view_widows', 'view_zones', 'view_projects',
            'view_education_interventions', 'view_healthcare_interventions', 'view_welfare_interventions',
            'view_loans', 'view_repayments',
            'imprest.transactions.view', 'imprest.funds.view',
            'imprest_view_transactions', 'imprest_view_funds',
            'view_reports', 'view_id_cards', 'admin_dashboard_access',
            'biometrics.view', 'orphan_education.analytics.view',
        ]);
    }
}
