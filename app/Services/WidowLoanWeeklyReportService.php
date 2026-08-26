<?php

namespace App\Services;

use App\Models\User;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only builder for the canonical WRL WEEKLY repayment report.
 *
 * The Foundation's weekly repayment report is a schedule-driven reconciliation
 * of every active WRL installment that fell due during a single reporting week.
 *
 * Semantics (each metric has exactly one meaning):
 *  - reporting week:  ISO Monday 00:00:00 .. Sunday 23:59:59.
 *  - a row is produced for EVERY loan that has an ACTIVE (non-superseded,
 *    non-waived) schedule whose due_date falls inside the week — including
 *    loans where ZERO repayment was collected. Partial and fully paid
 *    scheduled instalments are included too.
 *  - expected per loan: sum of that loan's active schedule amount_due for the
 *    week.
 *  - actual per loan     : sum of that loan's repayment.amount within the week.
 *  - shortfall per loan  : max(0, expected - actual).
 *  - Expected Total      : sum of ALL active schedule amount_due due in the week.
 *  - Collected Total     : sum of ALL repayment.amount posted in the week.
 *  - Shortfall Total     : max(0, Expected Total - Collected Total).
 *  - Remaining Balance   : sum of loan.outstanding_balance for the loans that
 *                          have a due schedule this week (clearly labelled,
 *                          NOT a weekly metric).
 *
 * This service never mutates any application data.
 *
 * @throws \RuntimeException if a caller-supplied zone cannot be honoured.
 */
class WidowLoanWeeklyReportService
{
    public function build(
        ?string $weekAnchor,
        ?string $zoneId,
        User $user,
        bool $canFilterZone,
    ): array {
        $now = Carbon::now();
        $anchor = $weekAnchor ? Carbon::parse($weekAnchor) : $now;

        $weekStart = $anchor->copy()->startOfWeek(); // ISO Monday
        $weekEnd = $anchor->copy()->endOfWeek();     // ISO Sunday

        $scopeZoneId = $canFilterZone
            ? ($zoneId ?: null)
            : ($user->coordinatedZone?->id);

        // The expected-driven population of loans that owe a weekly instalment.
        $schedules = \App\Models\WidowLoanSchedule::query()
            ->with(['widowLoan.widow.deceased.zone.coordinator'])
            ->whereNull('superseded_at')
            ->whereIn('status', [
                \App\Enums\WidowLoanScheduleStatus::PENDING,
                \App\Enums\WidowLoanScheduleStatus::OVERDUE,
                \App\Enums\WidowLoanScheduleStatus::PAID,
            ])
            ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($scopeZoneId, function ($query) use ($scopeZoneId) {
                $query->whereHas('widowLoan.widow.deceased', function ($q) use ($scopeZoneId) {
                    $q->where('zone_id', $scopeZoneId);
                });
            })
            ->get();

        // Group by loan so a loan with several instalments due in the same week
        // is reported once, with expected = sum of its due instalments.
        $schedulesByLoan = $schedules->groupBy('widow_loan_id');

        $loanIds = $schedulesByLoan->keys();

        // Actual posted repayments inside the week for the same loan scope.
        $repayments = WidowLoanRepayment::query()
            ->with(['widowLoan.widow.deceased.zone.coordinator', 'transaction.creator'])
            ->whereBetween('paid_at', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($scopeZoneId, function ($query) use ($scopeZoneId) {
                $query->whereHas('widowLoan.widow.deceased', function ($q) use ($scopeZoneId) {
                    $q->where('zone_id', $scopeZoneId);
                });
            })
            ->get();

        $actualByLoan = $repayments
            ->groupBy('widow_loan_id')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

        $rows = $schedulesByLoan->map(function (Collection $loanSchedules, $loanId) use ($actualByLoan) {
            /** @var \App\Models\WidowLoanSchedule $first */
            $first = $loanSchedules->first();
            $loan = $first->widowLoan;
            $widow = $loan->widow;

            $expected = round((float) $loanSchedules->sum('amount_due'), 2);
            $actual = round($actualByLoan->get($loanId, 0.0), 2);

            $dueDate = $loanSchedules
                ->sortBy('due_date')
                ->first()
                ?->due_date;

            return [
                'widow' => $widow,
                'loan' => $loan,
                'due_date' => $dueDate,
                'expected' => $expected,
                'actual' => $actual,
                'shortfall' => round(max(0, $expected - $actual), 2),
                'collected' => $actual > 0,
                'loan_reference' => \App\Http\Controllers\WidowLoanRepaymentController::loanReference($loan),
                'zone_name' => $widow?->deceased?->zone?->name ?? 'N/A',
                'coordinator' => $widow?->deceased?->zone?->coordinator?->name ?? 'N/A',
                'outstanding_balance' => round((float) ($loan->outstanding_balance ?? 0), 2),
            ];
        })->values();

        $expectedTotal = round((float) $schedulesByLoan
            ->map(fn (Collection $loanSchedules) => round((float) $loanSchedules->sum('amount_due'), 2))
            ->sum(), 2);

        $collectedTotal = round((float) $repayments->sum('amount'), 2);

        $remainingBalanceTotal = round((float) \App\Models\WidowLoan::query()
            ->whereIn('id', $loanIds)
            ->sum('outstanding_balance'), 2);

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'zone_id' => $scopeZoneId,
            'zone_name' => $scopeZoneId ? (Zone::find($scopeZoneId)?->name ?? 'N/A') : null,
            'rows' => $rows,
            'schedule_count' => $schedules->count(),
            'distinct_loans' => $schedulesByLoan->count(),
            'expected_total' => $expectedTotal,
            'collected_total' => $collectedTotal,
            'shortfall_total' => round(max(0, $expectedTotal - $collectedTotal), 2),
            'remaining_balance_total' => $remainingBalanceTotal,
            'generated_at' => $now,
        ];
    }
}
