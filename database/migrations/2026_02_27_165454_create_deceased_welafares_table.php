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
        Schema::create('deceased_welfare', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('welfare_id')->constrained('welfare');
            $table->foreignUuid('deceased_id')->constrained('deceased');
            $table->string('collection_status', 50)->default('PENDING');

            $table->unique(['welfare_id', 'deceased_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deceased_welafares');
    }
};
