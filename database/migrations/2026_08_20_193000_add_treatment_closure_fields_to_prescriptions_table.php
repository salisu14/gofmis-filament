<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('user_id');
            $table->timestamp('treated_at')->nullable()->after('status');
            $table->foreignUuid('treated_by_id')
                ->nullable()
                ->after('treated_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('treatment_notes')->nullable()->after('treated_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['treated_by_id']);
            $table->dropColumn(['status', 'treated_at', 'treated_by_id', 'treatment_notes']);
        });
    }
};
