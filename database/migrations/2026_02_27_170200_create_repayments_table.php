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
        Schema::create('repayments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('loan_id')
                ->constrained('widow_loans')
                ->cascadeOnDelete();

            $table->decimal('amount', 19, 2);
            $table->date('date_paid');
            $table->string('receipt_number', 100)->nullable();
            $table->enum('payment_method', ['CASH', 'BANK', 'TRANSFER']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
