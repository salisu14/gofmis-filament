<?php

namespace App\Http\Controllers;

use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WidowLoanRepaymentController extends Controller
{
    /**
     * Generate and download the A4 PDF receipt for a specific repayment.
     */
    public function downloadReceipt(Request $request, WidowLoanRepayment $repayment)
    {
        $this->authorizeLoanAccess($repayment->widowLoan);

        $repayment->load(['widowLoan.widow.deceased.zone', 'transaction']);

        $balance = max(
            0,
            (float) ($repayment->widowLoan->total_payable ?? $repayment->widowLoan->principal_amount)
            - (float) $repayment->widowLoan->repayments()
                ->where('paid_at', '<=', $repayment->paid_at)
                ->sum('amount')
        );

        $pdf = Pdf::loadView('filament.components.loan-receipt', [
            'record' => $repayment,
            'widow'  => $repayment->widowLoan->widow,
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
     * Generate and download the 58mm thermal WRL weekly repayment report for a specific loan.
     */
    public function downloadWeeklyThermalReport(Request $request, WidowLoan $loan)
    {
        $this->authorizeLoanAccess($loan);

        $loan->load(['widow.deceased.zone.coordinator', 'repayments.transaction.creator']);
        $repayment = $loan->repayments()->latest('paid_at')->first();

        if (! $repayment) {
            // Build a transient repayment object representing zero collection / initial state
            $repayment = new WidowLoanRepayment([
                'widow_loan_id' => $loan->id,
                'amount' => 0.00,
                'paid_at' => now(),
                'payment_method' => 'N/A',
                'receipt_number' => null,
            ]);
            $repayment->setRelation('widowLoan', $loan);
        }

        $pdf = Pdf::loadView('pdf.reports.wrl-weekly-repayment-thermal', [
            'repayment' => $repayment,
            'loan' => $loan,
            'company' => app(CompanyInformationService::class)->reportHeader(),
        ]);

        // 58mm paper width: 58mm / 25.4 * 72 = 164.41 pt
        $pdf->setPaper([0, 0, 164.41, 650], 'portrait');

        $filename = 'WRL-Weekly-Repayment-Thermal-'.($loan->reference_number ?? substr($loan->id, 0, 8)).'.pdf';

        return $pdf->download($filename);
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
