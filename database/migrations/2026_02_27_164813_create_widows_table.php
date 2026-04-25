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
        Schema::create('widows', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('nin', 20)->unique();
            $table->string('reg_no', 50)->unique();
            $table->string('skills', 255)->nullable();
            $table->boolean('is_eligible');
            $table->boolean('is_married');
            $table->text('address')->nullable();
            $table->text('picture_url')->nullable();

            $table->string('full_name')->nullable();

            $table->foreignUuid('deceased_id')
                ->nullable()
                ->constrained('deceased')
                ->nullOnDelete();

            $table->unsignedInteger('child_sequence')->after('deceased_id');

            // 🔥 enforce uniqueness per deceased
            $table->unique(['deceased_id', 'child_sequence']);

            $table->softDeletes(); // Added for audit trail
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widows');
    }
};
