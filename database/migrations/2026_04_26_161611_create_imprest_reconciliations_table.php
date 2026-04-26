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
        Schema::create('imprest_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fund_id')->constrained('imprest_funds');
            $table->date('reconciliation_date');
            $table->decimal('cash_on_hand', 12, 2);
            $table->decimal('receipts_total', 12, 2);
            $table->decimal('expected_balance', 12, 2);
            $table->decimal('actual_variance', 12, 2);
            $table->foreignUuid('auditor_id')->constrained('users');
            $table->foreignUuid('custodian_id')->constrained('users');
            $table->boolean('custodian_acknowledged')->default(false);
            $table->text('notes')->nullable();
            $table->text('variance_explanation')->nullable();
            $table->enum('status', ['in_progress', 'completed', 'flagged'])->default('in_progress');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'status', 'reconciliation_date'], 'idx_recon_fund_status_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imprest_reconciliations');
    }
};
