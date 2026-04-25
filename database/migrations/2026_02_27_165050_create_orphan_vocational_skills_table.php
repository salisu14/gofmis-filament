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
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('orphan_id')->constrained('orphans')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained('vocational_skills');
            $table->string('specify', 255)->nullable();
            $table->timestamps();
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
