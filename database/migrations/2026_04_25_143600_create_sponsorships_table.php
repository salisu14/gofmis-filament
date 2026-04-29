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
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('orphan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('sponsor_id')
                ->after('orphan_id')
                ->nullable() // Nullable for transition
                ->constrained('sponsors')
                ->nullOnDelete();

            $table->string('sponsor_name');
            $table->decimal('amount_committed', 12, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsorships');
    }
};
