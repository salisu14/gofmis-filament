<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a queryable key-version column to beneficiary_fingerprints.
     *
     * The on-disk envelope already carries a self-describing "biometric:v1:"
     * format prefix, but the column lets tooling (migration audit, capacity
     * planning, rotation scheduling) determine which encryption key version
     * authored each stored template without decrypting it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('beneficiary_fingerprints', 'key_version')) {
            Schema::table('beneficiary_fingerprints', function (Blueprint $table) {
                $table->unsignedTinyInteger('key_version')->nullable()->after('template_version');
            });

            // Existing rows were encrypted with Laravel's APP_KEY encrypted cast.
            // Indicate "legacy / unknown key version" with null rather than
            // claiming they were authored by a named biometric key version.
            \Illuminate\Support\Facades\DB::table('beneficiary_fingerprints')
                ->update(['key_version' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('beneficiary_fingerprints', function (Blueprint $table) {
            $table->dropColumn('key_version');
        });
    }
};
