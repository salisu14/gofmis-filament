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
        Schema::create('intervention_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('intervention_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('intervention_request_item_id')
                ->nullable() // Nullable in case the intervention item is unsolicited
                ->constrained('intervention_request_items')
                ->nullOnDelete();

            $table->string('item_name');
            $table->string('specification')->nullable();
            $table->integer('quantity')->default(1);

            $table->decimal('unit_value', 19, 2)->nullable();

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_items');
    }
};
