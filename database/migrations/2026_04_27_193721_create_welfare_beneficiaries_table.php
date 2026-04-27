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
        Schema::create('welfare_beneficiaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('welfare_package_id')->constrained('welfare_packages')->cascadeOnDelete();
            $table->foreignUuid('deceased_id')->constrained('deceased');
            $table->foreignUuid('suggested_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->string('status')->default(\App\Enums\BeneficiaryStatus::PENDING->value);
            $table->text('rejection_reason')->nullable();
            $table->string('collection_status')->default(\App\Enums\CollectionStatus::NOT_COLLECTED->value);
            $table->timestamp('collected_at')->nullable();
            $table->foreignUuid('collected_by')->nullable()->constrained('users');
            $table->text('collection_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['welfare_package_id', 'deceased_id'], 'unique_package_deceased');
            $table->index(['welfare_package_id', 'status']);
            $table->index(['collection_status', 'collected_at']);
            $table->index('suggested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('welfare_beneficiaries');
    }
};
