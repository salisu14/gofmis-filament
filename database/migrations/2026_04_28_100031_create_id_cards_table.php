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
        Schema::create('id_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('id_card_templates');
            $table->string('card_number')->unique();
            $table->string('qr_code_path');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->enum('status', ['draft', 'active', 'revoked', 'expired'])->default('draft');
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            // Use uuidMorphs instead of morphs to support UUID foreign keys
            $table->uuidMorphs('cardable'); // Creates cardable_type (string) and cardable_id (uuid)

            $table->index('card_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
