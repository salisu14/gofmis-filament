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
        Schema::create('company_information', function (Blueprint $table) {
            $table->id(); // Strictly singleton, will only ever contain ID 1

            $table->string('company_name')->default('Garko Orphans Foundation');
            $table->string('registration_no')->nullable()->comment('NGO / CAC Registration No.');
            $table->string('tax_registration_no')->nullable()->comment('TIN');

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 3)->default('NGA');

            // Contact
            $table->string('phone_no')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Primary Contact Person
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_title')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->string('contact_person_email')->nullable();

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();

            // Banking Details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('swift_code')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_information');
    }
};
