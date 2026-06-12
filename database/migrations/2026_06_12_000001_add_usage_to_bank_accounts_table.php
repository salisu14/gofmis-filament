<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('bank_accounts', 'usage')) {
                $table->string('usage')->default('general')->after('parent_bank_account_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('bank_accounts', 'usage')) {
                $table->dropIndex(['usage']);
                $table->dropColumn('usage');
            }
        });
    }
};
