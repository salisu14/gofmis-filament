<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orphan_educations', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique()->after('id');
        });

        Schema::table('education_fee_invoices', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique()->after('id');
        });

        Schema::table('education_fee_payments', function (Blueprint $table) {
            $table->foreignUuid('bank_account_id')
                ->nullable()
                ->after('education_fee_invoice_id')
                ->constrained()
                ->nullOnDelete();

            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('education_fee_payments', function (Blueprint $table) {
            $table->dropIndex(['reference']);
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('education_fee_invoices', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });

        Schema::table('orphan_educations', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
