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
        Schema::create('orphan_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name')->unique();

            // Reference to the user/staff who managed this entry
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Index for faster searching in dropdowns
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphan_classes');
    }
};
