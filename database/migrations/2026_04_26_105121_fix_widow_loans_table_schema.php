<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            // Check if status exists, if not add it
            if (!Schema::hasColumn('widow_loans', 'status')) {
                $table->enum('status', [
                    'draft',
                    'pending',
                    'approved',
                    'rejected',
                    'disbursed',
                    'completed',
                    'defaulted',
                ])->default('draft')->after('widow_id');
            }

            // Rename amount to principal_amount if it exists
            if (Schema::hasColumn('widow_loans', 'amount')) {
                $table->renameColumn('amount', 'principal_amount');
            }

            // Add missing tracking columns
            if (!Schema::hasColumn('widow_loans', 'duration_months')) {
                $table->integer('duration_months')->nullable()->after('principal_amount');
            }

            if (!Schema::hasColumn('widow_loans', 'total_payable')) {
                $table->decimal('total_payable', 15, 2)->nullable()->after('duration_months');
            }

            if (!Schema::hasColumn('widow_loans', 'total_paid')) {
                $table->decimal('total_paid', 15, 2)->default(0)->after('total_payable');
            }

            if (!Schema::hasColumn('widow_loans', 'outstanding_balance')) {
                $table->decimal('outstanding_balance', 15, 2)->nullable()->after('total_paid');
            }

            if (!Schema::hasColumn('widow_loans', 'disbursed_at')) {
                $table->timestamp('disbursed_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('widow_loans', 'approval_flow_id')) {
                $table->uuid('approval_flow_id')->nullable()->after('disbursed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (Schema::hasColumn('widow_loans', 'principal_amount')) {
                $table->renameColumn('principal_amount', 'amount');
            }
            
            $table->dropColumn([
                'status',
                'duration_months',
                'total_payable',
                'total_paid',
                'outstanding_balance',
                'disbursed_at',
                'approval_flow_id',
            ]);
        });
    }
};
