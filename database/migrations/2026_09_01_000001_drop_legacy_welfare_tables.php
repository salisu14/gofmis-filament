<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['welfare', 'deceased_welfare'];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $count = DB::table($table)->count();
            if ($count > 0) {
                throw new RuntimeException("Cannot drop legacy table '{$table}' because it contains {$count} rows. Please archive data before proceeding.");
            }
        }
        Schema::dropIfExists('deceased_welfare');
        Schema::dropIfExists('welfare');
    }

    /**
     * Reverse the migrations.
     *
     * Faithfully reproduces the ORIGINAL schemas created by:
     *   - 2026_02_27_164716_create_welfares_table.php
     *   - 2026_02_27_165454_create_deceased_welafares_table.php
     *
     * The original `welfare` table used a UUID primary key (not bigint), and
     * `deceased_welfare` used UUID foreign keys referencing it. Both tables
     * carried a unique constraint on (welfare_id, deceased_id). Recreation
     * order matters: `welfare` must exist before `deceased_welfare` because
     * the latter references it.
     */
    public function down(): void
    {
        if (! Schema::hasTable('welfare')) {
            Schema::create('welfare', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->date('date');
                $table->string('collection_status', 50);
                $table->string('welfare_status', 50);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('deceased_welfare')) {
            Schema::create('deceased_welfare', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('welfare_id')->constrained('welfare');
                $table->foreignUuid('deceased_id')->constrained('deceased');
                $table->string('collection_status', 50)->default('PENDING');

                $table->unique(['welfare_id', 'deceased_id']);
            });
        }
    }
};
