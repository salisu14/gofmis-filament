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
        Schema::create('sponsorship_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('sponsorship_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('orphan_education_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount_allocated', 12, 2);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsorship_allocations');
    }
};
