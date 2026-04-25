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
        Schema::create('deceased', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);

            // Remove the storedAs() - we'll handle this in the model
            $table->string('full_name')->nullable();

            $table->string('nin', 20)->unique();
            $table->string('reg_no', 30)->unique();

            $table->integer('number_of_orphans_left')->default(0);
            $table->integer('number_of_widows_left')->default(0);

            $table->string('guardian_name', 100);
            $table->string('guardian_phone', 100);

            $table->integer('deceased_age')->nullable();

            $table->text('address')->nullable();

            $table->enum('vulnerability_status', ['A', 'B', 'C']);

            $table->date('date_registered');

            $table->string('death_cause', 255)->nullable();
            $table->string('death_place', 255)->nullable();

            $table->string('occupation', 100)->nullable();

            $table->boolean('has_death_cert')->default(false);
            $table->string('death_cert_url', 255)->nullable();

            $table->foreignUuid('zone_id')
                ->nullable()
                ->constrained('zones');

            $table->timestamps();

            $table->index('vulnerability_status');
            $table->index('date_registered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deceased');
    }
};
