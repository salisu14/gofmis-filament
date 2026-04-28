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
        Schema::create('id_card_print_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_name');
            $table->enum('type', ['widow', 'orphan', 'mixed']);
            $table->json('filters'); // stored filter criteria
            $table->json('range')->nullable(); // specific ID range
            $table->integer('total_count');
            $table->integer('processed_count')->default(0);
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_card_print_batches');
    }
};
