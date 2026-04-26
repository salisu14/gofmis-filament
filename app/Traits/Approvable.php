<?php

namespace App\Traits;

use App\Models\ApprovalFlow;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait Approvable
{
    public function approvalFlow(): MorphOne
    {
        return $this->morphOne(ApprovalFlow::class, 'model');
    }

    public function isPendingApproval(): bool
    {
        return $this->approvalFlow?->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approvalFlow?->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approvalFlow?->status === 'rejected';
    }

    public function isAwaitingApproval(): bool
    {
        return $this->approvalFlow?->status === 'pending';
    }

    public function getCurrentApprovalStep()
    {
        return $this->approvalFlow?->currentStep();
    }
}
