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
            $table->timestamp('divorced_at')->nullable()->after('married_at');
            $table->dropUnique('widows_nin_unique');
            $table->unique(['deceased_id', 'nin'], 'widows_deceased_id_nin_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('widows', function (Blueprint $table) {
            $table->dropUnique('widows_deceased_id_nin_unique');
            $table->unique('nin', 'widows_nin_unique');
            $table->dropColumn('divorced_at');
        });
    }
};
