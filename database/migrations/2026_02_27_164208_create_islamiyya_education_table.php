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
        Schema::create('islamiyya_education', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->string('islamiyya_name', 255)->nullable();
            $table->string('class_form_master', 255)->nullable();
            $table->boolean('school_fee_eligible')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('islamiyya_education');
    }
};
