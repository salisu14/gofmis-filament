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
        Schema::create('zone_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('deceased_id')->constrained('deceased')->cascadeOnDelete();

            $table->foreignUuid('from_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignUuid('to_zone_id')->constrained('zones')->cascadeOnDelete();

            $table->foreignUuid('moved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_transfers');
    }
};
