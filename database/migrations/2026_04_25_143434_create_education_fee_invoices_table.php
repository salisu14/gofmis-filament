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
        Schema::create('education_fee_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Assuming orphan_educations table exists for tracking specific school enrollments
            $table->foreignUuid('orphan_education_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('period'); // e.g. "Term 1 2026"
            $table->string('status')->default('pending'); // pending, partial, paid, cancelled

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_fee_invoices');
    }
};
