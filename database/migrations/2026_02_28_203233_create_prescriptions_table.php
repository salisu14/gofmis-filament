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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doctor_name')->nullable();
            $table->string('illness');
            $table->decimal('lab_test_cost', 15, 2)->default(0);
            $table->decimal('drug_cost', 15, 2)->default(0);
            $table->date('prescription_date');
            $table->text('note')->nullable();

            // Polymorphic relationship: Can be Orphan or Widow
            $table->uuidMorphs('prescribable');

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Index for faster lookups on patient
            $table->index(['prescribable_id', 'prescribable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
