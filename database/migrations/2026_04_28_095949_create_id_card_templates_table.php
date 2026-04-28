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
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // e.g., "Standard Widow Card", "Orphan Card"
            $table->enum('type', ['widow', 'orphan']);
            $table->json('layout_config'); // margins, colors, font sizes
            $table->string('background_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
    }
};
