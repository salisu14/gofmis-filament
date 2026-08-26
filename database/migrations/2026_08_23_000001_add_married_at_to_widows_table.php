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
        Schema::table('widows', function (Blueprint $table) {
            $table->timestamp('married_at')->nullable()->after('is_married');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('widows', function (Blueprint $table) {
            $table->dropColumn('married_at');
        });
    }
};
