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
        Schema::create('widow_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_id')->constrained()->cascadeOnDelete();

            // Loan details
            $table->decimal('principal_amount', 15, 2);
            $table->integer('duration_months')->nullable();

            // Computed / tracking
            $table->decimal('total_payable', 15, 2)->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->nullable();

            // Status lifecycle
            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected',
                'disbursed',
                'completed',
                'defaulted',
            ])->default('draft');

            // Disbursement
            $table->timestamp('disbursed_at')->nullable();

            // Approval integration
            $table->uuid('approval_flow_id')->nullable(); // plugin uses this

            $table->text('purpose')->nullable();

            $table->boolean('fully_repaid')->default(false);

            $table->string('loan_agreement_url', 255)->nullable();
            $table->text('reject_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
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
