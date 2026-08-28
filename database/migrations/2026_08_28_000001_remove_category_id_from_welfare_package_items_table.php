<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('welfare_package_items', function (Blueprint $table) {
            // Drop foreign key & old composite unique constraint
            $table->dropForeign(['category_id']);
            $table->dropUnique('unique_package_item_category');
            $table->dropColumn('category_id');

            // Unique constraint on (welfare_package_id, item_id)
            $table->unique(['welfare_package_id', 'item_id'], 'unique_package_item');
        });
    }

    public function down(): void
    {
        Schema::table('welfare_package_items', function (Blueprint $table) {
            $table->dropUnique('unique_package_item');
            $table->foreignUuid('category_id')->nullable()->constrained('categories');
            $table->unique(['welfare_package_id', 'item_id', 'category_id'], 'unique_package_item_category');
        });
    }
};
