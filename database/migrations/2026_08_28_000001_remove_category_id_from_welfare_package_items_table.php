<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            try {
                Schema::create('welfare_package_items_temp', function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->foreignUuid('welfare_package_id')->constrained('welfare_packages')->cascadeOnDelete();
                    $table->foreignUuid('item_id')->constrained('items');
                    $table->unsignedInteger('quantity_per_family')->default(1);
                    $table->text('notes')->nullable();
                    $table->timestamps();

                    $table->unique(['welfare_package_id', 'item_id'], 'unique_package_item');
                    $table->index('welfare_package_id');
                });

                if (Schema::hasTable('welfare_package_items')) {
                    DB::statement('INSERT INTO welfare_package_items_temp (id, welfare_package_id, item_id, quantity_per_family, notes, created_at, updated_at) SELECT id, welfare_package_id, item_id, quantity_per_family, notes, created_at, updated_at FROM welfare_package_items');
                    Schema::drop('welfare_package_items');
                }

                Schema::rename('welfare_package_items_temp', 'welfare_package_items');
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        } else {
            Schema::table('welfare_package_items', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropUnique('unique_package_item_category');
                $table->dropColumn('category_id');
                $table->unique(['welfare_package_id', 'item_id'], 'unique_package_item');
            });
        }
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
