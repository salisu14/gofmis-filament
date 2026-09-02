<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orphan_educations', function (Blueprint $table) {
            $table->string('academic_session')->nullable()->after('class_level');
            $table->string('progression_decision')->nullable()->after('academic_session');
            $table->text('progression_reason')->nullable()->after('progression_decision');
            $table->foreignUuid('recorded_by_id')
                ->nullable()
                ->after('progression_reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['orphan_id', 'academic_session']);
            $table->index('progression_decision');
        });

        // Partial unique index enforcing at most ONE active enrollment per orphan per institution
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_oe_unique_current_orphan_inst ON orphan_educations (orphan_id, institution_id) WHERE is_current = true AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_oe_unique_current_orphan_inst');

        Schema::table('orphan_educations', function (Blueprint $table) {
            $table->dropForeign(['recorded_by_id']);
            $table->dropColumn([
                'academic_session',
                'progression_decision',
                'progression_reason',
                'recorded_by_id',
            ]);
        });
    }
};
