<?php

namespace App\Traits;

use App\Enums\LoanStatus;
use Carbon\Carbon;

trait HasLoanStatusTransitions
{
    public function approve(): void
    {
        $this->update([
            'status' => LoanStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => LoanStatus::REJECTED,
            'reject_reason' => $reason,
            'approved_at' => null, // Ensure approval is nullified
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => LoanStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function markAsCollected(): void
    {
        if (is_null($this->collected_at)) {
            $this->update(['collected_at' => now()]);
        }
    }

    public function unmarkAsCollected(): void
    {
        $this->update(['collected_at' => null]);
    }
}
