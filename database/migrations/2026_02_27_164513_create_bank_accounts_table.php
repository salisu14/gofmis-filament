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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('account_name', 100);
            $table->string('account_number', 50)->unique();

            // Financial columns aligned with the BankAccount model logic
            // Using 19, 2 for high precision (standard for financial systems)
            $table->decimal('opening_balance', 19, 2)->default(0);
            $table->decimal('ledger_balance', 19, 2)->default(0);
            $table->decimal('reserved_balance', 19, 2)->default(0);

            // Ownership
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Indexes for faster financial lookups
            $table->index('account_number');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
