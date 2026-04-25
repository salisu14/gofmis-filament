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
        Schema::create('widow_loans', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->foreignUuid('widow_id')->constrained('widows');
            $table->decimal('amount', 19, 2);
            $table->date('date_issued');
            $table->date('due_date')->nullable();
            $table->boolean('fully_repaid')->default(false);
            $table->string('purpose', 255)->nullable();
            $table->string('loan_agreement_url', 255)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widow_loans');
    }
};
