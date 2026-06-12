<?php

namespace App\Actions\Loan;

use App\Data\Loan\RecordRepaymentData;
use App\Events\LoanFullyRepaid;
use App\Models\BankAccount;
use App\Models\Repayment;
use App\Models\Loan;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;

class RecordLoanRepaymentAction
{
    /**
     * @throws \Throwable
     */
    public function execute(RecordRepaymentData $data): Repayment
    {
        return DB::transaction(function () use ($data) {
            // 1. Retrieve the Loan
            $loan = Loan::with('repayments')->findOrFail($data->loanId);

            // 2. Business Rule: Loan must be Approved and Collected before repayment
            if (is_null($loan->approved_at) || is_null($loan->collected_at)) {
                throw new Exception('Loan must be approved and collected before repayment.');
            }

            if (!is_null($loan->paid_at)) {
                throw new Exception('This loan has already been fully paid.');
            }

            // 3. Calculate Remaining Balance dynamically
            $totalPaidSoFar = $loan->repayments()->sum('amount');
            $remainingBalance = $loan->amount - $totalPaidSoFar;

            // 4. Validation: Payment cannot exceed remaining balance
            if ($data->amount > $remainingBalance) {
                throw new Exception("Repayment amount ({$data->amount}) exceeds remaining balance ({$remainingBalance}).");
            }

            // 5. Create Repayment Record
            $repayment = Repayment::create([
                'loan_id' => $loan->id,
                'widow_id' => $loan->widow_id, // For easier querying
                'amount' => $data->amount,
                'date_paid' => now(),
                'payment_method' => $data->paymentMethod,
                'receipt_number' => $data->receiptNumber,
                'user_id' => auth()->id(),
            ]);

            // 6. Update Bank through an auditable transaction.
            $bank = BankAccount::query()
                ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
                ->first();
            if ($bank) {
                Transaction::create([
                    'bank_account_id' => $bank->id,
                    'reference' => 'REP-'.strtoupper(substr($repayment->id, 0, 8)),
                    'date' => now(),
                    'type' => 'loan_repayment',
                    'amount' => $data->amount,
                    'description' => "Repayment for loan {$loan->id}",
                    'is_system' => true,
                ]);
            }

            // 7. Check if Loan is now Fully Paid
            $newTotalPaid = $totalPaidSoFar + $data->amount;

            // Use a small float comparison epsilon or allow slight overpayment,
            // but usually strict >= is fine for currency.
            if ($newTotalPaid >= $loan->amount) {
                $loan->markAsPaid(); // Uses the Trait defined in the previous step
            }

            return $repayment;
        });
    }
}
