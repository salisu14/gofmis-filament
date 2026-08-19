<?php

namespace App\Services;

use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanRecoveryStatus;
use App\Enums\WidowLoanStatus;
use App\Models\User;
use App\Models\WidowLoan;
use App\Models\WidowLoanRecoveryCase;
use Illuminate\Support\Facades\DB;

class WidowLoanDelinquencyService
{
    /**
     * Evaluate a single loan's delinquency metrics and performance status.
     */
    public function evaluateLoan(WidowLoan $loan): WidowLoan
    {
        // Written-off and Completed loans have terminal performance status
        if ($loan->status === WidowLoanStatus::WRITTEN_OFF) {
            $loan->update(['performance_status' => WidowLoanPerformanceStatus::WRITTEN_OFF]);

            return $loan;
        }

        if ($loan->status === WidowLoanStatus::COMPLETED || $loan->fully_repaid) {
            $loan->update([
                'performance_status' => WidowLoanPerformanceStatus::CURRENT,
                'days_past_due' => 0,
                'overdue_amount' => 0.00,
                'arrears_installments' => 0,
            ]);

            return $loan;
        }

        return DB::transaction(function () use ($loan) {
            $dpd = $this->calculateDaysPastDue($loan);
            $overdueAmount = $this->calculateOverdueAmount($loan);
            $arrearsInstallments = $this->calculateArrearsInstallments($loan);

            // Fetch last payment date
            $lastRepayment = $loan->repayments()->orderBy('paid_at', 'desc')->orderBy('created_at', 'desc')->first();
            $lastPaymentAt = $lastRepayment ? $lastRepayment->paid_at : null;

            // Determine first overdue date
            $firstOverdueAt = $loan->first_overdue_at;
            if ($dpd > 0 && is_null($firstOverdueAt)) {
                $firstOverdueAt = now();
            } elseif ($dpd === 0) {
                $firstOverdueAt = null;
            }

            // Check if there is an active approved relief period
            $hasActiveRelief = $loan->reliefPeriods()
                ->where('status', 'active')
                ->whereDate('starts_at', '<=', now())
                ->whereDate('ends_at', '>=', now())
                ->exists();

            // Check if restructured
            $hasApprovedRestructure = $loan->restructures()
                ->where('status', \App\Enums\WidowLoanRestructureStatus::APPLIED)
                ->exists();

            $delinquentDays = config('widow_loans.delinquent_after_days', 30);
            $defaultDays = config('widow_loans.default_after_days', 90);

            // Determine operational status
            if ($dpd === 0) {
                $status = WidowLoanPerformanceStatus::CURRENT;
            } elseif ($dpd < $delinquentDays) {
                $status = WidowLoanPerformanceStatus::OVERDUE;
            } elseif ($dpd < $defaultDays) {
                if ($loan->hardship_active || $hasActiveRelief) {
                    $status = WidowLoanPerformanceStatus::HARDSHIP;
                } elseif ($hasApprovedRestructure) {
                    $status = WidowLoanPerformanceStatus::RESTRUCTURED;
                } else {
                    $status = WidowLoanPerformanceStatus::DELINQUENT;
                }
            } else {
                // Exceeded default threshold
                if ($hasActiveRelief) {
                    // Relief prevents default
                    $status = WidowLoanPerformanceStatus::HARDSHIP;
                } else {
                    $status = WidowLoanPerformanceStatus::DEFAULTED;
                }
            }

            $updateData = [
                'days_past_due' => $dpd,
                'overdue_amount' => $overdueAmount,
                'arrears_installments' => $arrearsInstallments,
                'last_payment_at' => $lastPaymentAt,
                'first_overdue_at' => $firstOverdueAt,
                'performance_status' => $status,
            ];

            if ($status === WidowLoanPerformanceStatus::DEFAULTED) {
                if (is_null($loan->defaulted_at)) {
                    $updateData['defaulted_at'] = now();
                    $updateData['default_reason'] = "Exceeded default threshold of {$defaultDays} days.";
                }
            } else {
                // Cleared / cured from default
                $updateData['defaulted_at'] = null;
                $updateData['default_reason'] = null;
            }

            $loan->update($updateData);

            // Trigger recovery case opening if delinquent or defaulted
            if (in_array($status, [WidowLoanPerformanceStatus::DELINQUENT, WidowLoanPerformanceStatus::DEFAULTED])) {
                $this->ensureRecoveryCaseExists($loan);
            }

            return $loan;
        });
    }

    /**
     * Evaluate all active loans.
     */
    public function evaluateAllEligibleLoans(): array
    {
        $loans = WidowLoan::whereIn('status', [WidowLoanStatus::DISBURSED])->get();
        $processed = 0;

        foreach ($loans as $loan) {
            $this->evaluateLoan($loan);
            $processed++;
        }

        return [
            'processed_count' => $processed,
        ];
    }

    /**
     * DPD is based on the oldest unpaid, non-waived installment.
     */
    public function calculateDaysPastDue(WidowLoan $loan): int
    {
        $totalPaid = (float) $loan->total_paid;

        $schedules = $loan->schedules()
            ->whereNull('superseded_at')
            ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
            ->orderBy('installment_number')
            ->get();

        $runningRequired = 0.0;

        foreach ($schedules as $schedule) {
            $runningRequired += (float) $schedule->amount_due;

            if ($totalPaid + 0.01 < $runningRequired) {
                if ($schedule->due_date->isPast()) {
                    return (int) abs(now()->startOfDay()->diffInDays($schedule->due_date->startOfDay(), false));
                } else {
                    return 0; // Oldest unpaid is in the future
                }
            }
        }

        return 0;
    }

    /**
     * Overdue amount equals the sum of unpaid scheduled amounts whose due date has passed.
     */
    public function calculateOverdueAmount(WidowLoan $loan): float
    {
        $totalPaid = (float) $loan->total_paid;

        $schedules = $loan->schedules()
            ->whereNull('superseded_at')
            ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
            ->orderBy('installment_number')
            ->get();

        $overdueAmount = 0.0;
        $runningRequired = 0.0;

        foreach ($schedules as $schedule) {
            $runningRequired += (float) $schedule->amount_due;

            if ($schedule->due_date->isPast()) {
                if ($totalPaid < $runningRequired) {
                    $coveredForThis = max(0.0, $totalPaid - ($runningRequired - (float) $schedule->amount_due));
                    $unpaidForThis = (float) $schedule->amount_due - $coveredForThis;
                    $overdueAmount += $unpaidForThis;
                }
            }
        }

        return round($overdueAmount, 2);
    }

    /**
     * Count of unpaid scheduled installments whose due date has passed.
     */
    public function calculateArrearsInstallments(WidowLoan $loan): int
    {
        $totalPaid = (float) $loan->total_paid;

        $schedules = $loan->schedules()
            ->whereNull('superseded_at')
            ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
            ->orderBy('installment_number')
            ->get();

        $runningRequired = 0.0;
        $arrearsCount = 0;

        foreach ($schedules as $schedule) {
            $runningRequired += (float) $schedule->amount_due;

            if ($schedule->due_date->isPast() && $totalPaid + 0.01 < $runningRequired) {
                $arrearsCount++;
            }
        }

        return $arrearsCount;
    }

    /**
     * Ensure a recovery case exists for a delinquent/defaulted loan.
     */
    protected function ensureRecoveryCaseExists(WidowLoan $loan): void
    {
        $hasOpenCase = $loan->recoveryCases()
            ->whereNotIn('status', [WidowLoanRecoveryStatus::CLOSED, WidowLoanRecoveryStatus::RESOLVED])
            ->exists();

        if (! $hasOpenCase) {
            $openedBy = auth()->id() ?: User::role('admin')->first()?->id ?: User::first()?->id;

            if ($openedBy) {
                WidowLoanRecoveryCase::create([
                    'widow_loan_id' => $loan->id,
                    'opened_by' => $openedBy,
                    'opened_at' => now(),
                    'status' => WidowLoanRecoveryStatus::OPEN,
                    'priority' => $loan->performance_status === WidowLoanPerformanceStatus::DEFAULTED ? 'high' : 'medium',
                ]);
            }
        }
    }
}
