<?php

namespace App\Console\Commands;

use App\Enums\WidowLoanHardshipStatus;
use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanRecoveryStatus;
use App\Enums\WidowLoanRestructureStatus;
use App\Enums\WidowLoanStatus;
use App\Enums\WidowLoanWriteOffRecommendationStatus;
use App\Models\WidowLoan;
use App\Services\WidowLoanDelinquencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReconcileWidowLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'widow-loans:reconcile {--details} {--export=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform diagnostic audit to identify financial, schedule, and logic inconsistencies on widow loans';

    /**
     * Execute the console command.
     */
    public function handle(WidowLoanDelinquencyService $delinquencyService): int
    {
        $this->info('Starting Widow Loans Diagnostic Reconciliation...');

        $loans = WidowLoan::all();
        $inconsistencies = [];
        $totalChecked = 0;

        foreach ($loans as $loan) {
            $totalChecked++;
            $issues = [];

            // 1. Completed loans with outstanding balance
            if ($loan->status === WidowLoanStatus::COMPLETED && (float) $loan->outstanding_balance > 0) {
                $issues[] = 'Completed loan with non-zero outstanding balance (₦'.number_format($loan->outstanding_balance, 2).')';
            }

            // 2. Written-off loans with non-zero outstanding balance
            if ($loan->status === WidowLoanStatus::WRITTEN_OFF && (float) $loan->outstanding_balance > 0) {
                $issues[] = 'Written-off loan with non-zero outstanding balance (₦'.number_format($loan->outstanding_balance, 2).')';
            }

            // 3. Written-off loans marked fully repaid
            if ($loan->status === WidowLoanStatus::WRITTEN_OFF && $loan->fully_repaid) {
                $issues[] = 'Written-off loan marked fully repaid';
            }

            // 4. total_paid greater than total payable without explanation
            if ((float) $loan->total_paid > (float) $loan->total_payable) {
                $issues[] = 'Total paid (₦'.number_format($loan->total_paid, 2).') exceeds total payable (₦'.number_format($loan->total_payable, 2).')';
            }

            // 5. Schedule totals inconsistent with loan obligation
            $currentMaxVersion = $loan->schedules()->max('schedule_version') ?: 1;

            // Sum of current version schedules + paid schedules from superseded versions
            $currentSchedulesSum = (float) $loan->schedules()
                ->where('schedule_version', $currentMaxVersion)
                ->sum('amount_due');

            $paidSupersededSum = (float) $loan->schedules()
                ->where('schedule_version', '<', $currentMaxVersion)
                ->where('is_paid', true)
                ->sum('amount_due');

            $expectedTotal = $currentSchedulesSum + $paidSupersededSum;

            if (abs($expectedTotal - (float) $loan->total_payable) > 0.05) {
                $issues[] = 'Schedule totals (₦'.number_format($expectedTotal, 2).') inconsistent with total payable (₦'.number_format($loan->total_payable, 2).')';
            }

            // 6. Paid schedule mismatches
            $paidMismatches = $loan->schedules()
                ->where(function ($q) {
                    $q->where('is_paid', true)->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::PAID->value);
                })
                ->orWhere(function ($q) use ($loan) {
                    $q->where('widow_loan_id', $loan->id)
                        ->where('is_paid', false)
                        ->where('status', \App\Enums\WidowLoanScheduleStatus::PAID->value);
                })
                ->count();
            if ($paidMismatches > 0) {
                $issues[] = "{$paidMismatches} schedules have paid status/flag mismatch";
            }

            // 7. Waived schedule inconsistencies
            $waivedInconsistencies = $loan->schedules()
                ->where('status', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
                ->where('is_paid', true)
                ->count();
            if ($waivedInconsistencies > 0) {
                $issues[] = "{$waivedInconsistencies} waived schedules are marked as paid";
            }

            // 8. DPD inconsistent with schedules
            $calculatedDpd = $delinquencyService->calculateDaysPastDue($loan);
            if ($calculatedDpd !== (int) $loan->days_past_due) {
                $issues[] = "Days past due mismatch: Database has {$loan->days_past_due}, schedules indicate {$calculatedDpd}";
            }

            // 9. Performance status inconsistent with DPD
            // Only check active disbursed loans
            if ($loan->status === WidowLoanStatus::DISBURSED) {
                $hasActiveRelief = $loan->reliefPeriods()
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now())
                    ->exists();

                $delinquentDays = config('widow_loans.delinquent_after_days', 30);
                $defaultDays = config('widow_loans.default_after_days', 90);

                $expectedPerf = WidowLoanPerformanceStatus::CURRENT;
                if ($calculatedDpd > 0) {
                    if ($calculatedDpd < $delinquentDays) {
                        $expectedPerf = WidowLoanPerformanceStatus::OVERDUE;
                    } elseif ($calculatedDpd < $defaultDays) {
                        if ($loan->hardship_active || $hasActiveRelief) {
                            $expectedPerf = WidowLoanPerformanceStatus::HARDSHIP;
                        } elseif ($loan->restructures()->where('status', WidowLoanRestructureStatus::APPLIED)->exists()) {
                            $expectedPerf = WidowLoanPerformanceStatus::RESTRUCTURED;
                        } else {
                            $expectedPerf = WidowLoanPerformanceStatus::DELINQUENT;
                        }
                    } else {
                        if ($hasActiveRelief) {
                            $expectedPerf = WidowLoanPerformanceStatus::HARDSHIP;
                        } else {
                            $expectedPerf = WidowLoanPerformanceStatus::DEFAULTED;
                        }
                    }
                }

                if ($loan->performance_status !== $expectedPerf) {
                    $issues[] = "Performance status mismatch: Database has {$loan->performance_status->value}, expected {$expectedPerf->value} based on DPD and arrangements";
                }
            }

            // 10. Duplicate active recovery cases
            $activeRecoveryCount = $loan->recoveryCases()
                ->whereIn('status', [
                    WidowLoanRecoveryStatus::OPEN,
                    WidowLoanRecoveryStatus::IN_PROGRESS,
                    WidowLoanRecoveryStatus::PROMISE_TO_PAY,
                    WidowLoanRecoveryStatus::UNDER_HARDSHIP_REVIEW,
                    WidowLoanRecoveryStatus::RESTRUCTURED,
                    WidowLoanRecoveryStatus::ESCALATED,
                ])
                ->count();
            if ($activeRecoveryCount > 1) {
                $issues[] = "Duplicate active recovery cases found ({$activeRecoveryCount})";
            }

            // 11. Duplicate active hardship cases
            $activeHardshipCount = $loan->hardshipCases()
                ->whereIn('status', [
                    WidowLoanHardshipStatus::PENDING,
                    WidowLoanHardshipStatus::UNDER_REVIEW,
                    WidowLoanHardshipStatus::VERIFIED,
                    WidowLoanHardshipStatus::APPROVED,
                ])
                ->count();
            if ($activeHardshipCount > 1) {
                $issues[] = "Duplicate active hardship cases found ({$activeHardshipCount})";
            }

            // 12. Restructure schedule inconsistencies
            $appliedRestructureCount = $loan->restructures()
                ->where('status', WidowLoanRestructureStatus::APPLIED)
                ->count();
            if ($appliedRestructureCount > 0 && $currentMaxVersion <= 1) {
                $issues[] = 'Restructure is marked applied but schedule has not been versioned';
            }

            // 13. Write-off recommendation after actual write-off
            if ($loan->status === WidowLoanStatus::WRITTEN_OFF) {
                $pendingRecommendations = $loan->writeOffRecommendations()
                    ->whereIn('status', [
                        WidowLoanWriteOffRecommendationStatus::PENDING,
                        WidowLoanWriteOffRecommendationStatus::ENDORSED,
                    ])
                    ->count();
                if ($pendingRecommendations > 0) {
                    $issues[] = "Loan is written off but has {$pendingRecommendations} pending/endorsed recommendations";
                }
            }

            if (! empty($issues)) {
                $inconsistencies[] = [
                    'id' => $loan->id,
                    'widow' => $loan->widow?->full_name ?? 'N/A',
                    'status' => $loan->status->value,
                    'perf_status' => $loan->performance_status?->value ?? 'N/A',
                    'issues' => $issues,
                ];
            }
        }

        $this->info("Audit completed. Total checked: {$totalChecked}. Found issues in ".count($inconsistencies).' loans.');

        if (count($inconsistencies) > 0) {
            $tableData = [];
            foreach ($inconsistencies as $item) {
                $tableData[] = [
                    $item['id'],
                    $item['widow'],
                    $item['status'],
                    $item['perf_status'],
                    implode('; ', $item['issues']),
                ];
            }

            $this->table(
                ['Loan ID', 'Widow Name', 'Status', 'Perf Status', 'Issues'],
                $tableData
            );

            // Export logic
            if ($exportPath = $this->option('export')) {
                $content = json_encode($inconsistencies, JSON_PRETTY_PRINT);
                Storage::disk('local')->put($exportPath, $content);
                $this->info('Diagnostic log exported to: '.Storage::disk('local')->path($exportPath));
            }
        } else {
            $this->info('No inconsistencies found. Portfolio diagnostics are completely clean.');
        }

        return Command::SUCCESS;
    }
}
