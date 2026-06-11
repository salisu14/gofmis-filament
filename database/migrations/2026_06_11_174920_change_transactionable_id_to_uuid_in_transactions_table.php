<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Safely drop the index if it exists using raw SQL
        DB::statement('DROP INDEX IF EXISTS transactions_transactionable_type_transactionable_id_index');

        // 2. Safely drop the columns if they still exist
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'transactionable_type')) {
                $table->dropColumn('transactionable_type');
            }
            if (Schema::hasColumn('transactions', 'transactionable_id')) {
                $table->dropColumn('transactionable_id');
            }
        });

        // 3. Add the morph columns back as UUIDs
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'transactionable_type')) {
                $table->nullableUuidMorphs('transactionable');
            }
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transactions_transactionable_type_transactionable_id_index');

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'transactionable_type')) {
                $table->dropColumn('transactionable_type');
            }
            if (Schema::hasColumn('transactions', 'transactionable_id')) {
                $table->dropColumn('transactionable_id');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'transactionable_type')) {
                $table->nullableMorphs('transactionable');
            }
        });
    }
};
