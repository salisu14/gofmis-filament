<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widow_loan_relief_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->foreignUuid('hardship_case_id')
                ->nullable()
                ->constrained('widow_loan_hardship_cases')
                ->nullOnDelete();

            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('reason')->nullable();

            $table->foreignUuid('approved_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('approved_at');

            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widow_loan_relief_periods');
    }
};
