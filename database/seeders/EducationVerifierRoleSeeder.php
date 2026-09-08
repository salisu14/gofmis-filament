<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EducationVerifierRoleSeeder extends Seeder
{
    /**
     * Compatibility entry point: adds the full canonical RBAC definitions only.
     * Existing grants, users and organizational data are preserved.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Delegate to canonical RBAC seeder
        app(RolesAndPermissionsSeeder::class)->runPreservingExistingPermissions();
    }
}
