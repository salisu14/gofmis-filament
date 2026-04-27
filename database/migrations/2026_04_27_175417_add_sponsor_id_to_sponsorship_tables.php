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
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->foreignUuid('sponsor_id')
                ->after('orphan_id')
                ->nullable() // Nullable for transition
                ->constrained('sponsors')
                ->nullOnDelete();
            
            $table->dropColumn('sponsor_name');
        });

        Schema::table('sponsorship_allocations', function (Blueprint $table) {
            $table->foreignUuid('sponsor_id')
                ->after('sponsorship_id')
                ->nullable()
                ->constrained('sponsors')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorship_allocations', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
            $table->dropColumn('sponsor_id');
        });

        Schema::table('sponsorships', function (Blueprint $table) {
            $table->string('sponsor_name')->after('orphan_id')->nullable();
            $table->dropForeign(['sponsor_id']);
            $table->dropColumn('sponsor_id');
        });
    }
};
