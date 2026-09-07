<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('out_of_pocket_expenditures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->date('expenditure_date')->index();
            $table->foreignUuid('incurred_by_user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->string('payee_name')->nullable();
            $table->string('category')->index();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->boolean('reimbursement_required')->default(true);
            $table->string('reimbursement_status')->default('pending')->index(); // not_required, pending, reimbursed
            $table->foreignUuid('reimbursement_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignUuid('reimbursement_transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->string('approval_status')->default('draft')->index(); // draft, submitted, approved, rejected
            $table->foreignUuid('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reimbursed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reimbursed_at')->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_of_pocket_expenditures');
    }
};
