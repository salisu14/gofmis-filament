<?php

namespace App\Services;

use App\Data\Loan\CreateWidowLoanData;
use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Enums\WidowLoanStatus;
use App\Exceptions\InsufficientBankBalanceException;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\WidowLoanSchedule;
use Illuminate\Support\Facades\DB;

class WidowLoanService
{
    /**
     * Create a new widow loan application.
     */
    public function createLoan(CreateWidowLoanData $data): WidowLoan
    {
        // Check if widow can apply
        $widow = $data->widowId; // Assuming we get the widow model or id
        // For now, assume widow is passed or fetched

        // Validate bank balance
        $bank = BankAccount::findOrFail($data->bankAccountId);
        if (!$bank->hasSufficientFunds($data->principalAmount)) {
            throw new InsufficientBankBalanceException('Insufficient funds in the selected bank account.');
        }

        return DB::transaction(function () use ($data) {
            $loan = WidowLoan::create([
                'widow_id' => $data->widowId,
                'bank_account_id' => $data->bankAccountId,
                'principal_amount' => $data->principalAmount,
                'duration_months' => $data->durationMonths,
                'purpose' => $data->purpose,
                'status' => WidowLoanStatus::DRAFT,
            ]);

            // Generate schedule if duration is set
            if ($data->durationMonths) {
                $this->generateLoanSchedule($loan);
            }

            return $loan;
        });
    }

    /**
     * Approve and disburse the loan.
     * @throws \Throwable
     */
    public function disburseLoan(WidowLoan $loan): void
    {
        DB::transaction(function () use ($loan) {
            $bankAccount = $loan->bankAccount;
            if (!$bankAccount) {
                throw new \RuntimeException('Bank account is required before disbursement.');
            }

            $bankAccount->debit((float) $loan->principal_amount);

            $loan->update([
                'status' => WidowLoanStatus::DISBURSED,
                'disbursed_at' => now(),
            ]);

            // Create transaction for disbursement
            $transaction = Transaction::create([
                'bank_account_id' => $bankAccount->id,
                'reference' => 'DISB-' . $loan->id,
                'date' => now(),
                'type' => 'loan_disbursement',
                'amount' => $loan->principal_amount,
                'description' => 'Loan disbursement for widow loan ' . $loan->id,
            ]);

            // Add transaction lines (debit loan receivable, credit cash/bank)
            // Assuming accounts exist
            TransactionLine::create([
                'transaction_id' => $transaction->id,
                'account_id' => 1, // Loan receivable account
                'debit' => $loan->principal_amount,
                'credit' => 0,
            ]);

            TransactionLine::create([
                'transaction_id' => $transaction->id,
                'account_id' => 2, // Bank account
                'debit' => 0,
                'credit' => $loan->principal_amount,
            ]);
        });
    }

    /**
     * Record a repayment.
     */
    public function recordRepayment(RecordWidowLoanRepaymentData $data): WidowLoanRepayment
    {
        return DB::transaction(function () use ($data) {
            $loan = WidowLoan::with('bankAccount')->findOrFail($data->widowLoanId);
            $bankAccountId = $data->bankAccountId ?: $loan->bank_account_id;

            if (!$bankAccountId) {
                throw new \RuntimeException('Bank account is required to record repayment.');
            }

            $bankAccount = BankAccount::findOrFail($bankAccountId);

            $repayment = WidowLoanRepayment::create([
                'widow_loan_id' => $data->widowLoanId,
                'bank_account_id' => $bankAccount->id,
                'amount' => $data->amount,
                'paid_at' => $data->paidAt,
                'payment_method' => $data->paymentMethod,
                'notes' => $data->notes,
            ]);

            $bankAccount->credit((float) $data->amount);

            // Update loan totals
            $loan->increment('total_paid', $data->amount);
            $loan->decrement('outstanding_balance', $data->amount);

            // Check if fully repaid
            if ($loan->outstanding_balance <= 0) {
                $loan->update([
                    'status' => WidowLoanStatus::COMPLETED,
                    'fully_repaid' => true,
                ]);
            }

            // Create transaction
            $transaction = Transaction::create([
                'bank_account_id' => $bankAccount->id,
                'reference' => 'REP-' . $repayment->id,
                'date' => $data->paidAt,
                'type' => 'loan_repayment',
                'amount' => $data->amount,
                'description' => 'Repayment for widow loan ' . $loan->id,
            ]);

            $repayment->update(['transaction_id' => $transaction->id]);

            // Transaction lines
            TransactionLine::create([
                'transaction_id' => $transaction->id,
                'account_id' => 2, // Bank
                'debit' => $data->amount,
                'credit' => 0,
            ]);

            TransactionLine::create([
                'transaction_id' => $transaction->id,
                'account_id' => 1, // Loan receivable
                'debit' => 0,
                'credit' => $data->amount,
            ]);

            return $repayment;
        });
    }

    /**
     * Generate loan schedule.
     */
    private function generateLoanSchedule(WidowLoan $loan): void
    {
        $principal = $loan->principal_amount;
        $months = $loan->duration_months;
        $monthlyPayment = $principal / $months; // Simple equal installments

        for ($i = 1; $i <= $months; $i++) {
            WidowLoanSchedule::create([
                'widow_loan_id' => $loan->id,
                'installment_number' => $i,
                'amount_due' => $monthlyPayment,
                'due_date' => now()->addMonths($i)->toDateString(),
            ]);
        }

        $loan->update(['total_payable' => $principal]); // For now, no interest
    }
}
