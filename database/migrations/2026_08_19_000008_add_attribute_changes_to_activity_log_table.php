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
        if (Schema::hasTable('activities')) {
            if (! Schema::hasColumn('activities', 'attribute_changes')) {
                Schema::table('activities', function (Blueprint $table) {
                    $table->json('attribute_changes')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('activities')) {
            if (Schema::hasColumn('activities', 'attribute_changes')) {
                Schema::table('activities', function (Blueprint $table) {
                    $table->dropColumn('attribute_changes');
                });
            }
        }
    }
};
