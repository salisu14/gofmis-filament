<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->string('performance_status')->default('current')->index()->after('status');
            $table->timestamp('first_overdue_at')->nullable()->after('performance_status');
            $table->timestamp('last_payment_at')->nullable()->after('first_overdue_at');
            $table->integer('days_past_due')->default(0)->index()->after('last_payment_at');
            $table->decimal('overdue_amount', 15, 2)->default(0.00)->after('days_past_due');
            $table->integer('arrears_installments')->default(0)->after('overdue_amount');
            $table->timestamp('defaulted_at')->nullable()->after('arrears_installments');
            $table->text('default_reason')->nullable()->after('defaulted_at');
            $table->string('recovery_status')->nullable()->after('default_reason');
            $table->timestamp('last_recovery_action_at')->nullable()->after('recovery_status');
            $table->timestamp('next_recovery_action_at')->nullable()->after('last_recovery_action_at');
            $table->boolean('hardship_active')->default(false)->after('next_recovery_action_at');
        });
    }

    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->dropColumn([
                'performance_status',
                'first_overdue_at',
                'last_payment_at',
                'days_past_due',
                'overdue_amount',
                'arrears_installments',
                'defaulted_at',
                'default_reason',
                'recovery_status',
                'last_recovery_action_at',
                'next_recovery_action_at',
                'hardship_active',
            ]);
        });
    }
};
