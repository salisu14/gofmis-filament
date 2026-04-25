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
        Schema::create('orphans', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100);
            $table->enum('gender', ['MALE', 'FEMALE']);
            $table->boolean('is_eligible')->default(true);
            $table->boolean('is_married')->default(false);
            $table->timestamp('married_at')->nullable();
            $table->string('nin', 20)->unique()->nullable();
            $table->string('reg_no', 50)->unique();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->text('picture_url')->nullable();

            $table->string('full_name')->nullable();

            $table->foreignUuid('deceased_id')
                ->nullable()
                ->constrained('deceased')
                ->nullOnDelete();

            $table->unsignedInteger('child_sequence')->after('deceased_id');

            $table->foreignUuid('islamiyya_education_id')
                ->nullable()
                ->constrained('islamiyya_education')
                ->nullOnDelete();

            $table->foreignUuid('western_education_id')
                ->nullable()
                ->constrained('western_education')
                ->nullOnDelete();

            $table->string('birth_certificate_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['deceased_id', 'child_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphans');
    }
};
