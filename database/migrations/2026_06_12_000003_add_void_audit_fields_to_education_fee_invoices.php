<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education_fee_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('education_fee_invoices', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('education_fee_invoices', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('void_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('education_fee_invoices', function (Blueprint $table): void {
            foreach (['voided_at', 'void_reason'] as $column) {
                if (Schema::hasColumn('education_fee_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
