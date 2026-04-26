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
        Schema::create('imprest_replenishments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fund_id')->constrained('imprest_funds');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->decimal('receipts_total', 12, 2);
            $table->decimal('variance', 12, 2)->default(0);
            $table->foreignUuid('requested_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'processed'])->default('draft');
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'status'], 'idx_repl_fund_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imprest_replenishments');
    }
};
