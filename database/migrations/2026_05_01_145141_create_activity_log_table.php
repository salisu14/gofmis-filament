<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('activity_log');

        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // Subject (the model being logged)
            $table->uuid('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->index(['subject_id', 'subject_type'], 'subject');

            $table->string('event')->nullable();

            // Causer (the user who did the action)
            $table->uuid('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->index(['causer_id', 'causer_type'], 'causer');

            // ✅ Properties stores changes, old values, attributes
            $table->json('properties')->nullable();

            // ✅ Batch UUID for grouping related activities
            $table->uuid('batch_uuid')->nullable();

            $table->timestamps();

//            $table->index('log_name');
            $table->index('batch_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
