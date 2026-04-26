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
        // Fix approval_flows table
        Schema::table('approval_flows', function (Blueprint $table) {
            $table->dropColumn('id');
        });
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

        // Fix approval_steps table
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('id');
        });
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
        Schema::table('approval_flows', function (Blueprint $table) {
            $table->dropColumn([
                'id', 'model_type', 'model_id', 'status', 'current_step', 'total_steps', 
                'approver_id', 'rejection_reason', 'approved_at', 'rejected_at'
            ]);
        });
        Schema::table('approval_flows', function (Blueprint $table) {
            $table->id()->first();
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn([
                'id', 'approval_flow_id', 'step_number', 'role_required', 'status', 
                'approver_id', 'approved_at', 'rejected_at', 'rejection_reason', 'comments'
            ]);
        });
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};
