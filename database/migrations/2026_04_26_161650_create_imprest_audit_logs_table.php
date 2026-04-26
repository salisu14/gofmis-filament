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
        Schema::create('imprest_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();  // UUID primary key
            $table->string('auditable_type', 100);
            $table->uuid('auditable_id');   // UUID, not bigint!
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->string('action', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imprest_audit_logs');
    }
};
