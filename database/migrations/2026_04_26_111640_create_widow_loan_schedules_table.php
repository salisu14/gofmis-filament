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
        Schema::create('widow_loan_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relationship to the primary loan record
            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            // Tracking the order of payments
            $table->unsignedInteger('installment_number');

            // Financial details matching the model's decimal:2 cast
            $table->decimal('amount_due', 12, 2);
            $table->date('due_date');

            // Payment status tracking
            $table->boolean('is_paid')->default(false);

            $table->timestamps();

            // Indexes for performance on scheduled task lookups
            $table->index('due_date');
            $table->index(['widow_loan_id', 'is_paid']);

            $table->date('paid_at')
                ->nullable()
                ->after('is_paid')
                ->comment('The date this installment was actually paid. Null means unpaid.');

            // Ensure installment numbers don't duplicate for the same loan
            $table->unique(['widow_loan_id', 'installment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widow_loan_schedules');
    }
};
