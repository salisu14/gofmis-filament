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
        Schema::create('welfare', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->string('name', 255);
            $table->date('date');
            $table->string('collection_status', 50);
            $table->string('welfare_status', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('welfare');
    }
};
