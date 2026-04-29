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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('zone_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('zones')
                ->nullOnDelete();
        });

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
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn('zone_id');
        });

        Schema::table('zones', function (Blueprint $table) {
            if (Schema::hasColumn('zones', 'coordinator_id')) {
                $table->dropForeign(['coordinator_id']);
                $table->dropColumn('coordinator_id');
            }
        });
    }
};
