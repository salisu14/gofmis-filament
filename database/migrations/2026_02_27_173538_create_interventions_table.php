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
        Schema::create('interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable because interventions can be unsolicited (e.g., emergency aid)
            $table->foreignUuid('intervention_request_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignUuid('orphan_id')
                ->constrained()
                ->cascadeOnDelete();

            // Kept here to allow unsolicited interventions to have a type
            $table->foreignUuid('intervention_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('status')->default('draft');

            $table->timestampTz('disbursed_at')->nullable();

            $table->foreignUuid('disbursed_by')->nullable()->constrained('users');

            $table->timestampTz('collected_at')->nullable();
            $table->string('collected_by')->nullable();

            $table->string('support_document_url')->nullable();

            $table->timestampsTz();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
