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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // house, school, church, mosque, clinic, water, road, etc.
            $table->string('status')->default('planning'); // planning, approved, in_progress, on_hold, completed, cancelled
            $table->decimal('budget_allocated', 15, 2)->default(0);
            $table->decimal('budget_spent', 15, 2)->default(0);
            $table->foreignUuid('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('deceased_id')->nullable()->constrained('deceased')->nullOnDelete(); // For family-specific projects
            $table->string('location_address')->nullable();
            $table->foreignUuid('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
