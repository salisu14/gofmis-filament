<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_fingerprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('beneficiary');
            $table->string('finger_position');
            $table->text('encrypted_template');
            $table->string('template_format')->nullable();
            $table->string('template_version')->nullable();
            $table->integer('quality_score')->nullable();
            $table->string('source')->default('hardware');
            $table->string('device_manufacturer')->nullable();
            $table->string('device_model')->nullable();
            $table->string('device_serial')->nullable();
            $table->string('sdk_version')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });

        // Partial unique index to enforce that only ONE active record exists per beneficiary + finger_position,
        // while permitting multiple historical revoked (is_active = false) records.
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX beneficiary_fingerprints_active_unique ON beneficiary_fingerprints (beneficiary_type, beneficiary_id, finger_position) WHERE is_active = true');
        } else {
            \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX beneficiary_fingerprints_active_unique ON beneficiary_fingerprints (beneficiary_type, beneficiary_id, finger_position) WHERE is_active = 1');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_fingerprints');
    }
};
