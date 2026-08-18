<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loan_schedules', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('is_paid');
        });

        // Update existing schedules: if they are paid, set to 'paid'
        DB::table('widow_loan_schedules')
            ->where('is_paid', true)
            ->update(['status' => 'paid']);

        // Check if unpaid and overdue (due_date in the past)
        DB::table('widow_loan_schedules')
            ->where('is_paid', false)
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }

    public function down(): void
    {
        Schema::table('widow_loan_schedules', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
