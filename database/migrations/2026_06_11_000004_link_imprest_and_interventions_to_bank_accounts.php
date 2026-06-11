<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imprest_funds', function (Blueprint $table) {
            if (! Schema::hasColumn('imprest_funds', 'bank_account_id')) {
                $table->foreignUuid('bank_account_id')
                    ->nullable()
                    ->after('custodian_id')
                    ->constrained('bank_accounts')
                    ->nullOnDelete();
            }
        });

        Schema::table('interventions', function (Blueprint $table) {
            if (! Schema::hasColumn('interventions', 'bank_account_id')) {
                $table->foreignUuid('bank_account_id')
                    ->nullable()
                    ->after('intervention_type_id')
                    ->constrained('bank_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('interventions', 'amount')) {
                $table->decimal('amount', 15, 2)->nullable()->after('bank_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            if (Schema::hasColumn('interventions', 'bank_account_id')) {
                $table->dropConstrainedForeignId('bank_account_id');
            }

            if (Schema::hasColumn('interventions', 'amount')) {
                $table->dropColumn('amount');
            }
        });

        Schema::table('imprest_funds', function (Blueprint $table) {
            if (Schema::hasColumn('imprest_funds', 'bank_account_id')) {
                $table->dropConstrainedForeignId('bank_account_id');
            }
        });
    }
};
