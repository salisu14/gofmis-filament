<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widow_loan_recovery_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->foreignUuid('opened_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('opened_at');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');

            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('current_action')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_reason')->nullable();

            $table->timestamps();
        });

        Schema::create('widow_loan_recovery_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('recovery_case_id')
                ->constrained('widow_loan_recovery_cases')
                ->cascadeOnDelete();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->string('activity_type');
            $table->text('notes');
            $table->string('contact_method');
            $table->decimal('promise_amount', 15, 2)->nullable();
            $table->date('promise_date')->nullable();

            $table->foreignUuid('performed_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('performed_at');
            $table->timestamp('next_follow_up_at')->nullable();

            $table->timestamps();
        });

        Schema::create('widow_loan_promises', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('recovery_case_id')
                ->constrained('widow_loan_recovery_cases')
                ->cascadeOnDelete();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->decimal('promised_amount', 15, 2);
            $table->date('promised_date');
            $table->string('status')->default('open');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('broken_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widow_loan_promises');
        Schema::dropIfExists('widow_loan_recovery_activities');
        Schema::dropIfExists('widow_loan_recovery_cases');
    }
};
