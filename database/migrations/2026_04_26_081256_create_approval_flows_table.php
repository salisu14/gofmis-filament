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
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Polymorphic columns for the entity being approved (e.g., WidowLoan)
            $table->string('model_type');
            $table->uuid('model_id');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(1);

            // Finalization details
            $table->uuid('approver_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            // Indexes for polymorphic lookups
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_flows');
    }
};
