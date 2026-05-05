<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add collected_at timestamp to widow_loans.
     *
     * This column records the moment the widow physically received/collected
     * the disbursed funds — distinct from disbursed_at (when funds left the bank).
     * The loan status remains DISBURSED; collected_at is the confirmation signal.
     */
    public function up(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->timestamp('collected_at')
                ->nullable()
                ->after('disbursed_at')
                ->comment('When the widow physically confirmed receipt of the disbursed funds. Null means not yet collected.');
        });
    }

    public function down(): void
    {
        Schema::table('widow_loans', function (Blueprint $table) {
            $table->dropColumn('collected_at');
        });
    }
};
