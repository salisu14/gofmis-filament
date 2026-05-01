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
        Schema::create('zone_coordinator_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();

            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['zone_id', 'unassigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_coordinator_histories');
    }
};
