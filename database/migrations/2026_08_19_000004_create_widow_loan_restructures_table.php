<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widow_loan_restructures', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->foreignUuid('hardship_case_id')
                ->nullable()
                ->constrained('widow_loan_hardship_cases')
                ->nullOnDelete();

            $table->decimal('old_outstanding_balance', 15, 2);
            $table->integer('old_duration_remaining');
            $table->integer('new_duration');
            $table->string('new_repayment_frequency');
            $table->decimal('new_installment_amount', 15, 2);
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending_approval');

            $table->foreignUuid('requested_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widow_loan_restructures');
    }
};
