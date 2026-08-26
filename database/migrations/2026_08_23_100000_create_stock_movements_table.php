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
        if (! Schema::hasColumn('items', 'unit_of_measure')) {
            Schema::table('items', function (Blueprint $table) {
                $table->string('unit_of_measure')->nullable()->default('Units');
                $table->integer('reorder_level')->default(15);
                $table->boolean('is_active')->default(true);
            });
        }

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('movement_type');
            $table->integer('quantity'); // Positive for inflows, negative for outflows
            $table->timestamp('occurred_at');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['unit_of_measure', 'reorder_level', 'is_active']);
        });
    }
};
