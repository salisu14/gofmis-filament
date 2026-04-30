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
       // Add coordinator_id to zones if not exists
        Schema::table('zones', function (Blueprint $table) {
            if (!Schema::hasColumn('zones', 'coordinator_id')) {
                $table->foreignUuid('coordinator_id')
                    ->nullable()
                    ->unique()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            if (Schema::hasColumn('zones', 'coordinator_id')) {
                $table->dropForeign(['coordinator_id']);
                $table->dropColumn('coordinator_id');
            }
        });
    }
};
