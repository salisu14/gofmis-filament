<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('widow_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relationship to the widow
            $table->foreignUuid('widow_id')
                ->constrained('widows')
                ->cascadeOnDelete();

            $table->foreignUuid('disbursement_bank_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->comment('The bank account the widow receives disbursed funds into. Separate from the internal disbursing account.');

            $table->foreignUuid('repayment_bank_id')
                ->nullable()
                ->after('disbursement_bank_id')
                ->constrained('bank_accounts')
                ->nullOnDelete()
                ->comment('The foundation bank account where repayments should be credited.');

            // Core Loan Financials
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('total_payable', 15, 2)->nullable();

            // Tracking Columns (Synchronized with Ledger logic)
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->nullable();


            $table->timestamp('collected_at')
                ->nullable()
                ->after('disbursed_at')
                ->comment('When the widow physically confirmed receipt of the disbursed funds. Null means not yet collected.');

            // Schedule Configuration
            $table->integer('duration_months')->nullable();
            $table->string('repayment_frequency')->default('weekly');

            // Status Lifecycle
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected',
                'disbursed',
                'completed',
                'defaulted',
            ])->default('draft');

            // Process Timestamps & Integration
            $table->timestamp('disbursed_at')->nullable();
            $table->uuid('approval_flow_id')->nullable(); // Used by multi-step approval system

            // Narrative & Documentation
            $table->text('purpose')->nullable();
            $table->boolean('fully_repaid')->default(false);
            $table->string('loan_agreement_url', 255)->nullable();
            $table->text('reject_reason')->nullable();

            $table->date('date_issued')->nullable();
            $table->date('due_date')->nullable();

            // Standard Metadata
            $table->timestamps();
            $table->softDeletes();

            // Indexes for faster lookups in the Finance dashboard
            $table->index('status');
            $table->index('widow_id');
            $table->index('fully_repaid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widow_loans');
    }
};
