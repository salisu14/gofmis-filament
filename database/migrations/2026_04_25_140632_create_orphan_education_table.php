<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orphan_educations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('orphan_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('institution_id')->constrained()->cascadeOnDelete();

            // The "Contracted" rate
            $table->decimal('school_fee', 12, 2)->nullable();
            $table->string('fee_frequency')->nullable(); // monthly, termly, yearly

            // Support details
            $table->boolean('is_fee_supported')->default(false);
            $table->decimal('support_amount', 12, 2)->nullable();

            // Enrollment Context
            $table->foreignUuid('orphan_class_id')  // class id: Primary 3, etc.
                ->nullable()
                ->constrained('orphan_classes')
                ->cascadeOnDelete();

            $table->string('class_level')->nullable();

            $table->boolean('is_current')->default(true);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('orphan_class_id');
            $table->index(['orphan_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphan_institutions');
    }
};
