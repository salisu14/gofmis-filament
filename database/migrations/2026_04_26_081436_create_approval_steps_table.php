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
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->uuid('id')->primary()->first();
            $table->uuid('approval_flow_id')->after('id');
            $table->integer('step_number')->after('approval_flow_id');
            $table->string('role_required')->nullable()->after('step_number');
            $table->enum('status', ['pending', 'approved', 'rejected', 'waiting'])->default('waiting')->after('role_required');
            $table->uuid('approver_id')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approver_id');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->text('comments')->nullable()->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
