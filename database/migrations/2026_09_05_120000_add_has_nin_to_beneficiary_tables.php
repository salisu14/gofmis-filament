<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the has_nin flag to the three beneficiary tables and make nin
     * stored as a nullable string so the domain invariant
     * "has_nin = false => nin = NULL" can be satisfied.
     */
    public function up(): void
    {
        foreach (['deceased', 'widows', 'orphans'] as $table) {
            if (! Schema::hasColumn($table, 'has_nin')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->boolean('has_nin')->default(false);
                });
            }
        }

        // Backfill: any existing row with a NIN is considered to "have" one.
        foreach (['deceased', 'widows', 'orphans'] as $table) {
            DB::table($table)
                ->whereNotNull('nin')
                ->where('nin', '!=', '')
                ->update(['has_nin' => true]);

            DB::table($table)
                ->whereNull('nin')
                ->orWhere('nin', '=', '')
                ->update(['has_nin' => false]);
        }

        // Make nin nullable on deceased and widows (orphans is already nullable)
        // so it can be set to NULL when has_nin is false.
        Schema::table('deceased', function (Blueprint $blueprint) {
            $blueprint->string('nin', 20)->nullable()->change();
        });

        Schema::table('widows', function (Blueprint $blueprint) {
            $blueprint->string('nin', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['deceased', 'widows', 'orphans'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('has_nin');
            });
        }

        // Revert nullability. This is only safe when no rows hold a NULL nin,
        // which is guaranteed immediately after migrate:rollback for a feature
        // that has not yet stored optional-NIN rows.
        Schema::table('deceased', function (Blueprint $blueprint) {
            $blueprint->string('nin', 20)->nullable(false)->change();
        });

        Schema::table('widows', function (Blueprint $blueprint) {
            $blueprint->string('nin', 20)->nullable(false)->change();
        });
    }
};
