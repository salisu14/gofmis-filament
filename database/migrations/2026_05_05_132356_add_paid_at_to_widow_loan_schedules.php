<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add paid_at to widow_loan_schedules so we can record exactly when
     * each installment was settled, rather than just the boolean is_paid flag.
     */
    public function up(): void
    {
        Schema::table('widow_loan_schedules', function (Blueprint $table) {
            $table->date('paid_at')
                ->nullable()
                ->after('is_paid')
                ->comment('The date this installment was actually paid. Null means unpaid.');
        });
    }

    public function down(): void
    {
        Schema::table('widow_loan_schedules', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
