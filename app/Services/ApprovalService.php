<?php

namespace App\Services;

use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /**
     * @throws \Throwable
     */
    public function createApprovalWorkflow($model, array $steps): ApprovalFlow
    {
        return DB::transaction(function () use ($model, $steps) {

            $flow = ApprovalFlow::create([
                'model_type' => $model::class,
                'model_id' => $model->id,
                'status' => 'pending',
                'current_step' => 1,
                'total_steps' => count($steps),
            ]);

            foreach ($steps as $index => $step) {
                ApprovalStep::create([
                    'approval_flow_id' => $flow->id,
                    'step_number' => $index + 1,
                    'role_required' => $step['role'],
                    'status' => $index === 0 ? 'pending' : 'waiting',
                ]);
            }

            return $flow;
        });
    }

    /**
     * @throws \Throwable
     */
    public function approve(ApprovalFlow $flow, $user, ?string $comments = null): void
    {
        DB::transaction(function () use ($flow, $user, $comments) {
            $step = $flow->currentStep();

            if (!$step || $step->status !== 'pending') {
                throw new \Exception('Invalid approval step.');
            }

            // Approve step
            $step->update([
                'status' => 'approved',
                'approver_id' => $user->id,
                'approved_at' => now(),
                'comments' => $comments,
            ]);

            // Move to next step
            if ($flow->current_step < $flow->total_steps) {
                $flow->increment('current_step');

                $flow->steps()
                    ->where('step_number', $flow->current_step)
                    ->update(['status' => 'pending']);
            } else {
                // Final approval
                $flow->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approver_id' => $user->id,
                ]);

                // Hook into model
                if (method_exists($flow->approvable, 'onApproved')) {
                    $flow->approvable->onApproved($flow);
                }
            }
        });
    }

    /**
     * @throws \Throwable
     */
    public function reject(ApprovalFlow $flow, $user, string $reason, ?string $comments = null): void
    {
        DB::transaction(function () use ($flow, $user, $reason, $comments) {
            $step = $flow->currentStep();

            if (!$step || $step->status !== 'pending') {
                throw new \Exception('Invalid rejection step.');
            }

            $step->update([
                'status' => 'rejected',
                'approver_id' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'comments' => $comments,
            ]);

            $flow->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Hook into model
            if (method_exists($flow->approvable, 'onRejected')) {
                $flow->approvable->onRejected($flow);
            }
        });
    }
}
