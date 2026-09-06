<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE widow_loans DROP CONSTRAINT IF EXISTS widow_loans_status_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-instating the restrictive enum check constraint on rollback is omitted
        // to prevent migration failures if records with newer status values exist.
    }
};
