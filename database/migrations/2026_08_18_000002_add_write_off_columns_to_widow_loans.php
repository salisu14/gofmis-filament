<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->decimal('amount_written_off', 15, 2)->nullable()->after('outstanding_balance');
            $table->timestamp('written_off_at')->nullable()->after('amount_written_off');

            $table->foreignUuid('written_off_by')
                ->nullable()
                ->after('written_off_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('reapplication_allowed')->nullable()->after('written_off_by');

            $table->foreignUuid('reapplication_authorized_by')
                ->nullable()
                ->after('reapplication_allowed')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reapplication_authorized_at')->nullable()->after('reapplication_authorized_by');
        });
    }

    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->dropForeign(['written_off_by']);
            $table->dropForeign(['reapplication_authorized_by']);

            $table->dropColumn([
                'amount_written_off',
                'written_off_at',
                'written_off_by',
                'reapplication_allowed',
                'reapplication_authorized_by',
                'reapplication_authorized_at',
            ]);
        });
    }
};
