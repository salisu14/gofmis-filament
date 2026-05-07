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
        Schema::create('widow_loan_repayments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Link to the specific loan
            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            // Link to the accounting transaction record
            $table->foreignUuid('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();

            // Financial details
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('payment_method'); // cash, transfer, deduction

            $table->unsignedBigInteger('receipt_number')
                ->nullable()
                ->after('id')
                ->comment('Sequential receipt reference number, auto-assigned on creation.');

            // Narrative
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for financial auditing and reporting
            $table->index('paid_at');
            $table->index('payment_method');
            $table->index('widow_loan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widow_loan_repayments');
    }
};
