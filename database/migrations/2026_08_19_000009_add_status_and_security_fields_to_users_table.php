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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->index()->after('is_active');
            $table->timestamp('disabled_at')->nullable()->after('status');
            $table->uuid('disabled_by')->nullable()->after('disabled_at');
            $table->timestamp('suspended_at')->nullable()->after('disabled_by');
            $table->uuid('suspended_by')->nullable()->after('suspended_at');
            $table->text('suspension_reason')->nullable()->after('suspended_by');
            $table->timestamp('locked_at')->nullable()->after('suspension_reason');
            $table->boolean('password_reset_required')->default(false)->after('locked_at');

            $table->foreign('disabled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('suspended_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['disabled_by']);
            $table->dropForeign(['suspended_by']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'disabled_at',
                'disabled_by',
                'suspended_at',
                'suspended_by',
                'suspension_reason',
                'locked_at',
                'password_reset_required',
            ]);
        });
    }
};
