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
        Schema::create('western_education', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->string('level', 20)->nullable();
            $table->string('school_name', 255)->nullable();
            $table->string('class_level', 100)->nullable();
            $table->string('qualification', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('western_education');
    }
};
