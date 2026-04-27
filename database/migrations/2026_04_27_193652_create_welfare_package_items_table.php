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
        Schema::create('welfare_package_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('welfare_package_id')->constrained('welfare_packages')->cascadeOnDelete();
            $table->foreignUuid('item_id')->constrained('items');
            $table->foreignUuid('category_id')->constrained('categories');
            $table->unsignedInteger('quantity_per_family')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['welfare_package_id', 'item_id', 'category_id'], 'unique_package_item_category');
            $table->index('welfare_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('welfare_package_items');
    }
};
