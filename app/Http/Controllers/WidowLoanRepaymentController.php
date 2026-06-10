<?php

namespace App\Http\Controllers;

use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WidowLoanRepaymentController extends Controller
{
    /**
     * Generate and download the PDF receipt for a specific repayment.
     */
    public function downloadReceipt(Request $request, WidowLoanRepayment $repayment)
    {
        // 1. Authorization Check (Highly Recommended!)
        // Ensure the logged-in user is allowed to view this receipt.
        // You can use Policies: Gate::authorize('view', $repayment);
        // Or a simple check like below:
        if (!auth()->check()) {
            abort(403, 'You must be logged in to download receipts.');
        }

        // 2. Eager load relationships to prevent N+1 queries in the view
        $repayment->load(['widowLoan.widow.deceased.zone', 'transaction']);

        // 3. Calculate the historical balance at the time of this payment
        $balance = max(
            0,
            (float) $repayment->widowLoan->total_payable
            - (float) $repayment->widowLoan->repayments()
                ->where('paid_at', '<=', $repayment->paid_at)
                ->sum('amount')
        );

        // 4. Load the Blade view into DomPDF
        $pdf = Pdf::loadView('filament.components.loan-receipt', [
            'record' => $repayment,
            'widow'  => $repayment->widowLoan->widow,
            'balance' => $balance,
        ]);

        // 5. Set paper size
        $pdf->setPaper('A4', 'portrait');

        // 6. Return the PDF as a downloadable stream
        return $pdf->download("Receipt-{$repayment->receipt_number}.pdf");
    }

    public function downloadStatement(WidowLoan $loan)
    {
        if (!auth()->check()) {
            abort(403);
        }

        // Eager load relationships
        $loan->load(['widow.deceased.zone', 'repayments']);

        $pdf = Pdf::loadView('filament.components.loan-statement', [
            'loan' => $loan,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("Loan-Statement-{$loan->id}.pdf");
    }
}
