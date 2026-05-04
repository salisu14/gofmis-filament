<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')
                ->nullable()
                ->after('widow_id')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });

        Schema::table('widow_loan_repayments', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')
                ->nullable()
                ->after('widow_loan_id')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('widow_loan_repayments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('widow_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
