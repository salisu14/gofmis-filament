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
        Schema::table('deceased', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('age');
            $table->date('date_of_death')->nullable()->after('date_registered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deceased', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'date_of_death']);
        });
    }
};
