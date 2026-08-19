<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widow_loan_write_off_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->foreignUuid('hardship_case_id')
                ->nullable()
                ->constrained('widow_loan_hardship_cases')
                ->nullOnDelete();

            $table->foreignUuid('recovery_case_id')
                ->nullable()
                ->constrained('widow_loan_recovery_cases')
                ->nullOnDelete();

            $table->decimal('recommended_amount', 15, 2);
            $table->text('reason');

            $table->foreignUuid('recommended_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('recommended_at');

            $table->string('status')->default('pending');

            $table->foreignUuid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widow_loan_write_off_recommendations');
    }
};
