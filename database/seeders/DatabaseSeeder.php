<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            StatesTableSeeder::class,
            CitiesTableSeeder::class,
            TownsTableSeeder::class,
            ZonesTableSeeder::class,
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            UsersTableSeeder::class,
            ImprestSeeder::class,
            BankAccountsTableSeeder::class,
            ImprestPermissionSeeder::class,
            IllnessSeeder::class,
            MedicationsTableSeeder::class,
            EducationVerifierRoleSeeder::class,
            IdCardTemplateSeeder::class,
            OrphanClassesTableSeeder::class,
            WelfarePackageSeeder::class,
        ]);
    }
}
