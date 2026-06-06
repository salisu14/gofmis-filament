<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (! Schema::hasColumn('widow_loans', 'collected_by')) {
                $table->foreignUuid('collected_by')
                    ->nullable()
                    ->after('collected_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            if (Schema::hasColumn('widow_loans', 'collected_by')) {
                $table->dropConstrainedForeignId('collected_by');
            }
        });
    }
};
