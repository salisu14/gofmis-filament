<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loan_schedules', function (Blueprint $table) {
            $table->integer('schedule_version')->default(1)->after('status');
            $table->timestamp('superseded_at')->nullable()->after('schedule_version');

            $table->foreignUuid('superseded_by')
                ->nullable()
                ->after('superseded_at')
                ->constrained('widow_loan_restructures')
                ->nullOnDelete();

            $table->dropUnique(['widow_loan_id', 'installment_number']);
            $table->unique(['widow_loan_id', 'installment_number', 'schedule_version']);
        });
    }

    public function down(): void
    {
        Schema::table('widow_loan_schedules', function (Blueprint $table) {
            $table->dropUnique(['widow_loan_id', 'installment_number', 'schedule_version']);
            $table->unique(['widow_loan_id', 'installment_number']);

            $table->dropForeign(['superseded_by']);
            $table->dropColumn([
                'schedule_version',
                'superseded_at',
                'superseded_by',
            ]);
        });
    }
};
