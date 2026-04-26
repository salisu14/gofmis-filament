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
        Schema::create('imprest_funds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('custodian_id')->constrained('users');
            $table->string('location', 100);
            $table->decimal('authorized_amount', 12, 2);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imprest_funds');
    }
};
