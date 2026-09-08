<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with safe reference and RBAC seeders.
     */
    public function run(): void
    {
        // Accounts, geography, staff-owned catalogues and financial setup are
        // explicit organizational setup, never automatic deployment data.
        $this->call([
            RolesAndPermissionsSeeder::class,
            IllnessSeeder::class,
            IdCardTemplateSeeder::class,
            InterventionTypeSeeder::class,
        ]);
    }
}
