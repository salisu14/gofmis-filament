<?php

namespace App\Http\Controllers;

use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Services\Company\CompanyInformationService;
use App\Services\WidowLoanWeeklyReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WidowLoanRepaymentController extends Controller
{
    /**
     * Generate and download the A4 PDF receipt for a specific repayment.
     */
    public function downloadReceipt(Request $request, WidowLoanRepayment $repayment)
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $this->authorizeLoanAccess($repayment->widowLoan);

        $repayment->load(['widowLoan.widow.deceased.zone', 'transaction']);

        // Historical balance immediately after THIS repayment, using the
        // deterministic (paid_at, receipt_number) ordering from the model
        // (never a loose same-day sum that depends on creation-time ties).
        $balance = $repayment->balance_after;

        $pdf = Pdf::loadView('filament.components.loan-receipt', [
            'record' => $repayment,
            'widow' => $repayment->widowLoan->widow,
            'balance' => $balance,
            'company' => app(CompanyInformationService::class)->reportHeader(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("Receipt-{$repayment->receipt_number}.pdf");
    }

    /**
     * Generate and download the A4 loan statement.
     */
    public function downloadStatement(WidowLoan $loan)
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $this->authorizeLoanAccess($loan);

        if (! $loan->repayments()->exists()) {
            abort(403, 'Loan statement cannot be downloaded until repayment has started.');
        }

        $loan->load(['widow.deceased.zone', 'repayments']);

        $pdf = Pdf::loadView('filament.components.loan-statement', [
            'loan' => $loan,
            'company' => app(CompanyInformationService::class)->reportHeader(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("Loan-Statement-{$loan->id}.pdf");
    }

    /**
     * Generate and download the 58mm thermal WRL weekly repayment receipt report for a specific repayment.
     */
    public function downloadThermalReceipt(Request $request, WidowLoanRepayment $repayment)
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $loan = $repayment->widowLoan()->withoutGlobalScopes()->first();

        if (! $loan) {
            abort(404, 'Associated loan record not found.');
        }

        $this->authorizeLoanAccess($loan);

        $repayment->load(['widowLoan.widow.deceased.zone.coordinator', 'transaction.creator']);

        $pdf = Pdf::loadView('pdf.reports.wrl-weekly-repayment-thermal', [
            'repayment' => $repayment,
            'loan' => $loan,
            'company' => app(CompanyInformationService::class)->reportHeader(),
        ]);

        // 58mm paper width: 58mm / 25.4 * 72 = 164.41 pt
        $pdf->setPaper([0, 0, 164.41, 650], 'portrait');

        $filename = 'WRL-Thermal-Repayment-'.($repayment->receipt_number ?? substr($repayment->id, 0, 8)).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate and download the TRUE 58mm thermal WRL WEEKLY repayment report.
     *
     * This is an aggregate reconciliation of every eligible repayment recorded
     * within a single reporting week (ISO Monday–Sunday), across the
     * organization (admin / super_admin) or a single coordinator zone.
     *
     * It is intentionally distinct from any individual repayment receipt:
     *  - repayments.receipt.download        -> A4 per-repayment receipt
     *  - repayments.thermal-receipt.download -> 58mm per-repayment receipt
     *  - wrl.weekly.download                -> 58mm WEEKLY aggregate report
     */
    public function downloadWeeklyReport(Request $request)
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $canFilterZone = $user->hasAnyRole(['super_admin', 'admin']);
        $requestedZone = $request->query('zone');

        // Coordinators may never request another zone's records.
        if (! $canFilterZone && $requestedZone) {
            abort(403, 'Unauthorized zone access.');
        }

        $company = app(CompanyInformationService::class)->reportHeader();

        $report = app(WidowLoanWeeklyReportService::class)->build(
            weekAnchor: $request->query('week'),
            zoneId: $requestedZone,
            user: $user,
            canFilterZone: $canFilterZone,
        );

        $pdf = Pdf::loadView('pdf.reports.wrl-weekly-repayment-report-thermal', [
            'rows' => $report['rows'],
            'weekStart' => $report['week_start'],
            'weekEnd' => $report['week_end'],
            'zone' => $report['zone_name'],
            'scheduleCount' => $report['schedule_count'],
            'repaymentCount' => $report['rows']->sum(fn ($row) => $row['collected'] ? 1 : 0),
            'distinctLoans' => $report['distinct_loans'],
            'expectedTotal' => $report['expected_total'],
            'collectedTotal' => $report['collected_total'],
            'shortfallTotal' => $report['shortfall_total'],
            'remainingBalanceTotal' => $report['remaining_balance_total'],
            'company' => $company,
            'generatedAt' => $report['generated_at'],
        ]);

        // 58mm paper width: 58mm / 25.4 * 72 = 164.41 pt.
        // Thermal output is continuous-feed, so height is set tall enough to
        // avoid clipping multi-row weeks while printing on a single receipt.
        $pdf->setPaper([0, 0, 164.41, 1500], 'portrait');

        $filename = 'WRL-Weekly-Report-'.$report['week_start']->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Canonical, stable loan reference used wherever no dedicated reference
     * column exists on the loan. Mirrors the convention used by the financial
     * services (REP-/DISB- prefixes use the same UUID short form).
     */
    public static function loanReference(WidowLoan $loan, ?string $prefix = 'LOAN'): string
    {
        return $prefix.'-'.strtoupper(substr((string) $loan->id, 0, 8));
    }

    /**
     * Enforce authentication and coordinator zone isolation.
     */
    protected function authorizeLoanAccess(WidowLoan $loan): void
    {
        if (! auth()->check()) {
            abort(403, 'Unauthorized.');
        }

        $user = auth()->user();

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return;
        }

        $userZoneId = $user->coordinatedZone?->id;
        $widow = $loan->widow()->withoutGlobalScopes()->first();
        $deceased = $widow?->deceased()->withoutGlobalScopes()->first();
        $loanZoneId = $deceased?->zone_id;

        if (! $userZoneId || $userZoneId !== $loanZoneId) {
            abort(403, 'Unauthorized zone access.');
        }
    }
}
