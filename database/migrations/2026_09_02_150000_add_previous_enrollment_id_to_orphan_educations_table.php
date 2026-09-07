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
        if (! Schema::hasColumn('orphan_educations', 'previous_enrollment_id')) {
            Schema::table('orphan_educations', function (Blueprint $table) {
                $table->foreignUuid('previous_enrollment_id')
                    ->nullable()
                    ->after('orphan_id')
                    ->constrained('orphan_educations')
                    ->nullOnDelete();

                $table->index('previous_enrollment_id');
                $table->index('started_at');
                $table->index('ended_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orphan_educations', 'previous_enrollment_id')) {
            Schema::table('orphan_educations', function (Blueprint $table) {
                $table->dropForeign(['previous_enrollment_id']);
                $table->dropColumn('previous_enrollment_id');
            });
        }
    }
};
