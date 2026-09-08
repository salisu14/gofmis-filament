<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Compatibility entry point: adds the full canonical RBAC definitions only.
     * Existing grants, users and organizational data are preserved.
     */
    public function run()
    {
        app(RolesAndPermissionsSeeder::class)->runPreservingExistingPermissions();
    }
}
