<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basic Info
            $table->string('name')->unique();
            $table->text('description')->nullable();

            // Self-referencing Foreign Key for Nesting
            $table->foreignUuid('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            // Ownership
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Index for hierarchical lookups
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
