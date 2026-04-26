<?php

use App\Enums\PaymentMethod;
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
        Schema::create('imprest_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fund_id')->constrained('imprest_funds');
            $table->date('date');
            $table->string('deceased_id', 50)->nullable();
            $table->string('name', 255);
            $table->string('item_service', 255);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->string('voucher_no', 50)->unique();
            $table->boolean('receipt_attached')->default(false);
            $table->foreignUuid('custodian_id')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->string('category', 100);
            $table->enum(
                'payment_method',
                array_column(PaymentMethod::cases(), 'value')
            )->default(PaymentMethod::CASH->value);
            $table->enum('status', ['pending', 'active', 'voided', 'rejected'])->default('pending');
            $table->text('void_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'date']);
            $table->index(['deceased_id', 'status']);
            $table->index('voucher_no');
            $table->index(['fund_id', 'status', 'date'], 'idx_fund_status_date');
            $table->index(['deceased_id', 'status'], 'idx_deceased_status');
            $table->fullText(['name', 'item_service'], 'idx_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imprest_transactions');
    }
};
