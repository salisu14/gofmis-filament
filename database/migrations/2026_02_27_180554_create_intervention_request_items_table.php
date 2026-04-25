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
        Schema::create('intervention_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('intervention_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('item_name');
            $table->string('specification')->nullable();
            $table->integer('quantity')->default(1);

            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_request_items');
    }
};
