<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widow_loan_write_offs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('widow_loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->decimal('original_outstanding_balance', 15, 2);
            $table->decimal('amount_written_off', 15, 2);

            $table->text('write_off_reason');
            $table->text('write_off_verification_notes')->nullable();
            $table->string('write_off_document_path')->nullable();

            $table->foreignUuid('authorized_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('authorized_at');
            $table->timestamps();

            // Unique index for one write-off per loan
            $table->unique('widow_loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widow_loan_write_offs');
    }
};
