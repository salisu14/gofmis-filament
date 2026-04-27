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
            $table->uuid('id')->primary();

            // Personal Information
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('full_name')->nullable();

            $table->enum('gender', ['MALE', 'FEMALE']);
            $table->date('birth_date')->nullable();
            $table->integer('age')->nullable();

            // Identifiers
            $table->string('nin', 20)->unique()->nullable();
            $table->string('reg_no', 50)->unique();

            // Status & Eligibility
            $table->boolean('is_eligible')->default(true);
            $table->string('status')->default('active'); // e.g., active, pending, inactive
            $table->text('rejection_reason')->nullable();

            // Marital Status
            $table->boolean('is_married')->default(false);
            $table->timestamp('married_at')->nullable();

            // Contact & Assets
            $table->text('address')->nullable();
            $table->text('picture_url')->nullable();
            $table->boolean('has_birth_cert')->default(false);
            $table->string('birth_certificate_path', 255)->nullable();

            // Relationships
            $table->foreignUuid('deceased_id')
                ->nullable()
                ->constrained('deceased')
                ->nullOnDelete();

            // Sequential tracking of children for a deceased parent
            $table->unsignedInteger('child_sequence')->default(1);

            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index('gender');
            $table->index('is_eligible');
            $table->index('status');
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
