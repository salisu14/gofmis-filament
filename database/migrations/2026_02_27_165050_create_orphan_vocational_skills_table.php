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
        Schema::create('orphan_vocational_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('orphan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vocational_skill_id')->constrained('vocational_skills')->cascadeOnDelete();

            $table->string('specify')->nullable();

            $table->timestamps();

            $table->unique(['orphan_id', 'vocational_skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphan_vocational_skills');
    }
};
