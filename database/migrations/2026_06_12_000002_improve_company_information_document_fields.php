<?php

use App\Models\CompanyInformation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            if (! Schema::hasColumn('company_information', 'trading_name')) {
                $table->string('trading_name')->nullable()->after('company_name');
            }

            if (! Schema::hasColumn('company_information', 'fiscal_year_start_month')) {
                $table->unsignedTinyInteger('fiscal_year_start_month')->nullable()->after('swift_code');
            }
        });

        DB::table('company_information')
            ->where('id', CompanyInformation::SINGLETON_ID)
            ->whereNull('address_line_1')
            ->update([
                'address_line_1' => CompanyInformation::DEFAULT_ADDRESS_LINE_1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            if (Schema::hasColumn('company_information', 'fiscal_year_start_month')) {
                $table->dropColumn('fiscal_year_start_month');
            }

            if (Schema::hasColumn('company_information', 'trading_name')) {
                $table->dropColumn('trading_name');
            }
        });
    }
};
