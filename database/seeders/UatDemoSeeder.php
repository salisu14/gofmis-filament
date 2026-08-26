<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Explicit UAT / demo dataset seeder.
 *
 * This seeder is INTENTIONALLY NOT part of DatabaseSeeder and MUST NOT run in
 * production. It is executed explicitly when a deterministic UAT/demo dataset
 * is wanted:
 *
 *     php artisan db:seed --class=UatDemoSeeder
 *
 * All child seeders are deterministic and idempotent: running them more than
 * once must never duplicate users, households, widows, orphans, items, stock
 * openings, welfare nominations/collections, loans, repayments, or
 * intervention history.
 */
class UatDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the UAT dataset.
     */
    public function run(): void
    {
        $this->assertNotProduction();

        $this->call([
            UatHouseholdSeeder::class,
            UatInventorySeeder::class,
            UatWelfareSeeder::class,
            UatEducationHealthcareSeeder::class,
            UatWidowLoanSeeder::class,
        ]);
    }

    /**
     * Refuse to run in a production environment.
     */
    protected function assertNotProduction(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'UatDemoSeeder is a development/UAT-only dataset and must never run in production.'
            );
        }
    }
}
