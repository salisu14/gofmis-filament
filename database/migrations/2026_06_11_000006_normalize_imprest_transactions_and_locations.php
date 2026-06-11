<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imprest_funds', function (Blueprint $table) {
            $table->foreignUuid('zone_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('zones')
                ->nullOnDelete();
        });

        Schema::table('imprest_transactions', function (Blueprint $table) {
            $table->string('expense_type', 20)->default('service')->after('name');
            $table->foreignUuid('item_id')
                ->nullable()
                ->after('expense_type')
                ->constrained('items')
                ->nullOnDelete();
            $table->string('service_description')->nullable()->after('item_id');

            $table->index('expense_type');
        });
    }

    public function down(): void
    {
        Schema::table('imprest_transactions', function (Blueprint $table) {
            $table->dropIndex(['expense_type']);
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn(['expense_type', 'service_description']);
        });

        Schema::table('imprest_funds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });
    }
};
