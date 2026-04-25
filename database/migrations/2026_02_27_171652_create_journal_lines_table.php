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
        Schema::create('journal_lines', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->foreignUuid('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnDelete();

            $table->foreignUuid('ledger_account_id')
                ->constrained('ledgers')
                ->restrictOnDelete();

            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);

            $table->timestampsTz();

            $table->string('description')->nullable();
            $table->unsignedInteger('line_number');

            // Indexes
            $table->index('journal_entry_id');
            $table->index('ledger_account_id');

            $table->unique(['journal_entry_id', 'line_number']);
        });

        // Add CHECK constraint using raw SQL
        DB::statement('
            ALTER TABLE journal_lines
            ADD CONSTRAINT chk_debit_credit_exclusive
            CHECK (
                debit >= 0 AND
                credit >= 0 AND
                (
                    (debit > 0 AND credit = 0) OR
                    (credit > 0 AND debit = 0)
                )
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
