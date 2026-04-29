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
        Schema::table('approval_flows', function (Blueprint $table) {
            $table->uuid('id')->primary()->first();
            $table->string('model_type')->after('id');
            $table->uuid('model_id')->after('model_type');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('model_id');
            $table->integer('current_step')->default(1)->after('status');
            $table->integer('total_steps')->default(1)->after('current_step');
            $table->uuid('approver_id')->nullable()->after('total_steps');
            $table->text('rejection_reason')->nullable()->after('approver_id');
            $table->timestamp('approved_at')->nullable()->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_flows');
    }
};
