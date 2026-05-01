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
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('approval_flow_id')
                ->constrained('approval_flows')
                ->cascadeOnDelete();

            $table->integer('step_number');
            $table->string('role_required')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'waiting'])
                ->default('waiting');

            $table->uuid('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            // Index for faster lookups when processing flows
            $table->index(['approval_flow_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
