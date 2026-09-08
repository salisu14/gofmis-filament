<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ImprestPermissionSeeder extends Seeder
{
    /**
     * Compatibility entry point: adds the full canonical RBAC definitions only.
     * Existing grants, users and organizational data are preserved.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Delegate to the canonical RBAC seeder to ensure safe, complete, and non-destructive seeding
        $this->callWith(RolesAndPermissionsSeeder::class, ['preserveExistingPermissions' => true]);
    }
}
