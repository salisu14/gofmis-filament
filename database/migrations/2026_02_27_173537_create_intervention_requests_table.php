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
        Schema::create('intervention_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('orphan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('intervention_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table->text('rejection_reason')->nullable();

            $table->string('status')->default('pending');

            $table->timestamp('request_date')->nullable();

            $table->string('verification_status')->default('unverified'); // verified, unverified, flagged

            $table->timestampTz('requested_at')->useCurrent();

            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();

            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestampTz('approved_at')->nullable();

            $table->foreignUuid('verified_by')->nullable()->constrained('users');
            $table->timestampTz('verified_at')->nullable();

            // Detailed notes from verifier
            $table->text('verification_notes')
                ->nullable()
                ->after('verified_by');

            // Documents uploaded during verification (school reports, home visit photos, etc.)
            $table->json('verification_documents')
                ->nullable()
                ->after('verification_notes');

            $table->timestampsTz();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_requests');
    }
};
