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
        Schema::create('education_fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('education_fee_invoice_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method'); // cash, bank, transfer
            $table->string('reference')->nullable(); // transaction id or receipt number

            $table->softDeletes();
            $table->timestamps();

            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_fee_payments');
    }
};
