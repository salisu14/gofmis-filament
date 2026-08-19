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
            $table->timestamp('mfa_enabled_at')->nullable()->after('app_authentication_recovery_codes');
            $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_enabled_at');
            $table->boolean('mfa_enrollment_required')->default(false)->after('mfa_confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_enabled_at', 'mfa_confirmed_at', 'mfa_enrollment_required']);
        });
    }
};
