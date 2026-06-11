<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (! Schema::hasColumn('widow_loans', 'collector_name')) {
                $table->string('collector_name')->nullable()->after('collected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (Schema::hasColumn('widow_loans', 'collector_name')) {
                $table->dropColumn('collector_name');
            }
        });
    }
};
