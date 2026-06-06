<?php

namespace App\Services;

use App\Data\Loan\CreateWidowLoanData;
use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Enums\WidowLoanStatus;
use App\Exceptions\InsufficientBankBalanceException;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use Illuminate\Support\Facades\DB;

class WidowLoanService
{
    /**
     * Create a new widow loan application (DRAFT status).
     *
     * We do NOT generate the repayment schedule here. The schedule is anchored
     * to the actual disbursement date, so it is generated inside disburseLoan().
     */
    public function createLoan(CreateWidowLoanData $data): WidowLoan
    {
        $widow = Widow::findOrFail($data->widowId);

        if (! $widow->canApplyForLoan()) {
            throw new \RuntimeException('This widow is not eligible to apply for a new loan.');
        }

        return DB::transaction(function () use ($data) {
            return WidowLoan::create([
                'widow_id' => $data->widowId,
                'bank_account_id' => $data->bankAccountId,
                'disbursement_bank_id' => $data->disbursementBankId,
                'repayment_bank_id' => $data->repaymentBankId,
                'principal_amount' => $data->principalAmount,
                'total_payable' => $data->principalAmount, // No interest by default
                'duration_months' => $data->durationMonths,
                'repayment_frequency' => $data->repaymentFrequency ?? 'weekly',
                'purpose' => $data->purpose,
                'status' => WidowLoanStatus::DRAFT,
                'outstanding_balance' => $data->principalAmount,
            ]);
        });
    }

    /**
     * Submit a draft loan for approval.
     *
     * This method:
     *  1. Creates the approval workflow.
     *  2. Reserves the principal amount in the foundation's bank account.
     *  3. Transitions status from DRAFT → PENDING.
     *
     * @throws InsufficientBankBalanceException|\Throwable
     */
    public function submitForApproval(WidowLoan $loan, array $approvers): void
    {
        if (! $loan->canSubmitForApproval()) {
            throw new \RuntimeException('Loan is not in a state that can be submitted for approval.');
        }

        $bankAccount = $loan->bankAccount;
        if (! $bankAccount) {
            throw new \RuntimeException('A bank account must be assigned before submission.');
        }

        DB::transaction(function () use ($loan, $approvers, $bankAccount) {
            // Reserve the funds in the bank account
            $bankAccount->reserve((float) $loan->principal_amount);

            // Create the approval workflow
            app(ApprovalService::class)->createApprovalWorkflow($loan, $approvers);

            // Transition status
            $loan->update(['status' => WidowLoanStatus::PENDING]);
        });
    }

    /**
     * Handle loan rejection by releasing the reserved funds back to the bank ledger.
     */
    public function handleRejection(WidowLoan $loan): void
    {
        $bankAccount = $loan->bankAccount;
        if ($bankAccount && $loan->principal_amount > 0) {
            $bankAccount->unreserve((float) $loan->principal_amount);
        }
    }

    /**
     * Disburse an approved loan.
     *
     * Steps:
     *  1. Validate loan is in APPROVED state.
     *  2. Validate bank has sufficient funds.
     *  3. Debit the bank account.
     *  4. Update loan status to DISBURSED and record disbursed_at.
     *  5. Create a disbursement Transaction record.
     *  6. Generate the repayment schedule (anchored to disbursed_at).
     *
     * @throws InsufficientBankBalanceException|\Throwable
     */
    public function disburseLoan(WidowLoan $loan): void
    {
        if (! $loan->canDisburse()) {
            throw new \RuntimeException(
                "Loan cannot be disbursed. Current status: {$loan->status->getLabel()}."
            );
        }

        $bankAccount = $loan->bankAccount;
        if (! $bankAccount) {
            throw new \RuntimeException('A bank account must be assigned before disbursement.');
        }

        DB::transaction(function () use ($loan, $bankAccount) {
            // Debit the disbursing bank account (and unreserve the funds)
            $bankAccount->disburse((float) $loan->principal_amount);

            $disbursedAt = now();

            // Update loan status
            $loan->update([
                'status' => WidowLoanStatus::DISBURSED,
                'disbursed_at' => $disbursedAt,
            ]);

            // Create a disbursement Transaction record
            Transaction::create([
                'bank_account_id' => $bankAccount->id,
                'reference' => 'DISB-'.strtoupper(substr($loan->id, 0, 8)),
                'date' => $disbursedAt,
                'type' => 'loan_disbursement',
                'amount' => $loan->principal_amount,
                'description' => "Loan disbursement for widow: {$loan->widow->full_name}",
            ]);

            // Generate repayment schedule anchored to the actual disbursement date.
            // The loan must be refreshed so disbursed_at is up-to-date before calling generateLedger.
            $loan->refresh()->generateLedger();
        });
    }

    /**
     * Mark a disbursed loan as collected — i.e. the widow has physically confirmed
     * receipt of the funds. The loan status remains DISBURSED; the collected_at
     * timestamp is the sole confirmation signal.
     *
     * @throws \RuntimeException|\Throwable
     */
    public function collectLoan(WidowLoan $loan, ?string $collectedBy = null): void
    {
        if (! $loan->canCollect()) {
            throw new \RuntimeException(
                "Loan cannot be marked as collected. Current status: {$loan->status->getLabel()}."
            );
        }

        // Only set the timestamp — status stays DISBURSED
        $loan->update([
            'collected_at' => now(),
            'collected_by' => $collectedBy ?? auth()->id(),
        ]);
    }

    /**
     * Record a repayment instalment against a disbursed/collected loan.
     *
     * Steps:
     *  1. Validate loan is in a repayable state.
     *  2. Credit the receiving bank account.
     *  3. Create a WidowLoanRepayment record.
     *  4. Create a Transaction record (no hardcoded journal lines).
     *  5. Call refreshBalance() once to recalculate totals and sync schedule flags.
     */
    public function recordRepayment(RecordWidowLoanRepaymentData $data): WidowLoanRepayment
    {
        return DB::transaction(function () use ($data) {
            $loan = WidowLoan::with(['bankAccount', 'repaymentBank'])->findOrFail($data->widowLoanId);

            if (! $loan->canRecordRepayment()) {
                throw new \RuntimeException(
                    'Repayments can only be recorded after the widow has collected the loan and before it is fully repaid.'
                );
            }

            $remainingBalance = (float) $loan->outstanding_balance;

            if ((float) $data->amount > $remainingBalance) {
                throw new \RuntimeException(
                    'Repayment amount exceeds the outstanding balance of ₦'.number_format($remainingBalance, 2).'.'
                );
            }

            $bankAccountId = $data->bankAccountId ?: ($loan->repayment_bank_id ?: $loan->bank_account_id);
            if (! $bankAccountId) {
                throw new \RuntimeException('A bank account is required to record a repayment.');
            }

            $bankAccount = BankAccount::findOrFail($bankAccountId);

            // Credit the receiving bank account with the repayment
            $bankAccount->credit((float) $data->amount);

            // Auto-assign the next sequential receipt number
            $nextReceiptNumber = WidowLoanRepayment::max('receipt_number') + 1;

            // Create the repayment record
            $repayment = WidowLoanRepayment::create([
                'widow_loan_id' => $data->widowLoanId,
                'bank_account_id' => $bankAccount->id,
                'receipt_number' => $nextReceiptNumber,
                'amount' => $data->amount,
                'paid_at' => $data->paidAt,
                'payment_method' => $data->paymentMethod,
                'notes' => $data->notes,
            ]);

            // Create a transaction record for audit trail
            $transaction = Transaction::create([
                'bank_account_id' => $bankAccount->id,
                'reference' => 'REP-'.strtoupper(substr($repayment->id, 0, 8)),
                'date' => $data->paidAt,
                'type' => 'loan_repayment',
                'amount' => $data->amount,
                'description' => "Repayment for widow loan: {$loan->widow->full_name}",
            ]);

            $repayment->update(['transaction_id' => $transaction->id]);

            // Single authoritative balance recalculation.
            // Do NOT manually increment/decrement loan totals — refreshBalance() handles it all.
            $loan->refreshBalance();

            return $repayment->fresh();
        });
    }
}
