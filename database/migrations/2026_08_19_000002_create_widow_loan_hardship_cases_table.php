<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widow_loan_hardship_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->foreignUuid('widow_id')
                ->constrained('widows')
                ->cascadeOnDelete();

            $table->foreignUuid('reported_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('reason_category');
            $table->text('reason_details');
            $table->text('verification_notes')->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('recommended_action')->nullable();

            $table->foreignUuid('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignUuid('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widow_loan_hardship_cases');
    }
};
