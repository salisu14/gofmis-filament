<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            if (! Schema::hasColumn('company_information', 'report_signatory_name')) {
                $table->string('report_signatory_name')->nullable()->after('swift_code');
            }

            if (! Schema::hasColumn('company_information', 'report_signatory_title')) {
                $table->string('report_signatory_title')->nullable()->after('report_signatory_name');
            }

            if (! Schema::hasColumn('company_information', 'report_signature_path')) {
                $table->string('report_signature_path')->nullable()->after('report_signatory_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_information', function (Blueprint $table) {
            if (Schema::hasColumn('company_information', 'report_signature_path')) {
                $table->dropColumn('report_signature_path');
            }

            if (Schema::hasColumn('company_information', 'report_signatory_title')) {
                $table->dropColumn('report_signatory_title');
            }

            if (Schema::hasColumn('company_information', 'report_signatory_name')) {
                $table->dropColumn('report_signatory_name');
            }
        });
    }
};
