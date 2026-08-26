<?php

namespace Database\Seeders;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanScheduleStatus;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\WidowLoanSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Deterministic UAT Widows Revolving Loan (WRL) scenarios.
 *
 * Loans are created directly (documented historical-state setup) rather than
 * via the full WidowLoanService lifecycle, because exercising the service
 * chain (create -> submit -> approve -> disburse -> collect) would require
 * interleaving approval workflows and actor sessions. The canonical
 * reconciliation method WidowLoan::refreshBalance() IS used so that
 * total_paid / outstanding_balance / schedule paid-flags reconcile exactly
 * with the repayment records — which is what future 58mm receipt printing
 * and reporting will rely on.
 *
 * All dates are anchored to a fixed constant so that the same seed command
 * always produces the same dates and the same logical scenarios.
 *
 * Scenarios:
 *   1. Approved + disbursed active loan with several weekly repayments.
 *   2. Disbursed loan with partial remaining balance.
 *   3. Fully repaid (completed) loan.
 *   4. Pending approval lifecycle example.
 *   5. DRAFT loan (not yet submitted).
 *
 * Idempotency: loans are keyed by (widow + deterministic purpose); repayments
 * are keyed by a deterministic per-installment reference so a second run never
 * duplicates them.
 */
class UatWidowLoanSeeder extends Seeder
{
    /** Fixed anchor date for deterministic schedule/repayment dates. */
    protected const ANCHOR_DATE = '2026-07-01';

    public function run(): void
    {
        $this->ensureBankAccounts();

        $this->activeLoanWithRepayments();
        $this->partialBalanceLoan();
        $this->fullyRepaidLoan();
        $this->pendingApprovalLoan();
        $this->draftLoan();
    }

    protected function ensureBankAccounts(): void
    {
        $user = User::where('email', 'admin@admin.com')->first()
            ?? User::where('email', 'sadmin@admin.com')->first();

        BankAccount::firstOrCreate(
            ['account_number' => '1000000001'],
            [
                'account_name' => 'WRL Disbursement Account',
                'opening_balance' => 5_000_000,
                'ledger_balance' => 5_000_000,
                'reserved_balance' => 0,
                'user_id' => $user->id,
                'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
            ]
        );

        BankAccount::firstOrCreate(
            ['account_number' => '1000000002'],
            [
                'account_name' => 'WRL Repayment Account',
                'opening_balance' => 0,
                'ledger_balance' => 0,
                'reserved_balance' => 0,
                'user_id' => $user->id,
                'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
            ]
        );
    }

    protected function anchor(): Carbon
    {
        return Carbon::parse(self::ANCHOR_DATE);
    }

    protected function widow(string $regNo): Widow
    {
        $widow = Widow::where('reg_no', $regNo)->first();
        if (! $widow) {
            throw new \RuntimeException("Widow [{$regNo}] not found. Run UatHouseholdSeeder first.");
        }

        return $widow;
    }

    protected function bankAccount(string $accountNumber): BankAccount
    {
        return BankAccount::where('account_number', $accountNumber)->first();
    }

    /**
     * Find an existing loan by a deterministic natural key, else create it.
     */
    protected function loan(Widow $widow, string $purpose): WidowLoan
    {
        $existing = WidowLoan::where('widow_id', $widow->id)
            ->where('purpose', $purpose)
            ->first();

        if ($existing) {
            return $existing;
        }

        return WidowLoan::create([
            'widow_id' => $widow->id,
            'purpose' => $purpose,
            'principal_amount' => 0,
            'total_payable' => 0,
            'outstanding_balance' => 0,
            'duration_months' => 12,
            'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
            'status' => WidowLoanStatus::DRAFT,
        ]);
    }

    /**
     * Scenario 1: approved + disbursed active loan with several weekly
     * repayments (installments 1-3 of 48 paid).
     */
    protected function activeLoanWithRepayments(): void
    {
        $widow = $this->widow('UAT-WID-001');
        $principal = 60_000;
        $duration = 12; // months -> 48 weekly installments
        $installment = round($principal / 48, 2);
        $disbursedAt = $this->anchor()->subWeeks(6);

        $loan = $this->loan($widow, 'Small trading business support');
        if ((float) $loan->principal_amount != $principal) {
            $loan->update([
                'principal_amount' => $principal,
                'total_payable' => $principal,
                'outstanding_balance' => $principal,
                'duration_months' => $duration,
                'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
                'bank_account_id' => $this->bankAccount('1000000001')->id,
                'disbursement_bank_id' => $this->bankAccount('1000000001')->id,
                'repayment_bank_id' => $this->bankAccount('1000000002')->id,
                'status' => WidowLoanStatus::DISBURSED,
                'disbursed_at' => $disbursedAt,
                'collected_at' => $disbursedAt->copy()->addDay(),
                'performance_status' => WidowLoanPerformanceStatus::CURRENT,
            ]);
        }

        $this->generateSchedule($loan, $disbursedAt);

        // Three weekly repayments.
        for ($i = 1; $i <= 3; $i++) {
            $this->repayment($loan, $installment, $disbursedAt->copy()->addWeeks($i), $i);
        }

        $loan->refreshBalance();
    }

    /**
     * Scenario 2: disbursed loan with partial remaining balance (half repaid).
     */
    protected function partialBalanceLoan(): void
    {
        $widow = $this->widow('UAT-WID-002');
        $principal = 40_000;
        $duration = 6; // 24 weekly installments
        $installment = round($principal / 24, 2);
        $disbursedAt = $this->anchor()->subWeeks(12);

        $loan = $this->loan($widow, 'Agricultural inputs support');
        if ((float) $loan->principal_amount != $principal) {
            $loan->update([
                'principal_amount' => $principal,
                'total_payable' => $principal,
                'outstanding_balance' => $principal,
                'duration_months' => $duration,
                'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
                'bank_account_id' => $this->bankAccount('1000000001')->id,
                'disbursement_bank_id' => $this->bankAccount('1000000001')->id,
                'repayment_bank_id' => $this->bankAccount('1000000002')->id,
                'status' => WidowLoanStatus::DISBURSED,
                'disbursed_at' => $disbursedAt,
                'collected_at' => $disbursedAt->copy()->addDay(),
                'performance_status' => WidowLoanPerformanceStatus::CURRENT,
            ]);
        }

        $this->generateSchedule($loan, $disbursedAt);

        // Twelve weekly repayments (half of the 24 installments).
        for ($i = 1; $i <= 12; $i++) {
            $this->repayment($loan, $installment, $disbursedAt->copy()->addWeeks($i), $i);
        }

        $loan->refreshBalance();
    }

    /**
     * Scenario 3: fully repaid (completed) loan.
     */
    protected function fullyRepaidLoan(): void
    {
        $widow = $this->widow('UAT-WID-003');
        $principal = 25_000;
        $duration = 3; // 12 weekly installments
        $installment = round($principal / 12, 2);
        $disbursedAt = $this->anchor()->subWeeks(16);

        $loan = $this->loan($widow, 'Petty trading startup');
        if ((float) $loan->principal_amount != $principal) {
            $loan->update([
                'principal_amount' => $principal,
                'total_payable' => $principal,
                'outstanding_balance' => $principal,
                'duration_months' => $duration,
                'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
                'bank_account_id' => $this->bankAccount('1000000001')->id,
                'disbursement_bank_id' => $this->bankAccount('1000000001')->id,
                'repayment_bank_id' => $this->bankAccount('1000000002')->id,
                'status' => WidowLoanStatus::DISBURSED,
                'disbursed_at' => $disbursedAt,
                'collected_at' => $disbursedAt->copy()->addDay(),
                'performance_status' => WidowLoanPerformanceStatus::CURRENT,
            ]);
        }

        $this->generateSchedule($loan, $disbursedAt);

        // Pay all 12 installments (last one absorbs rounding).
        for ($i = 1; $i <= 12; $i++) {
            $schedule = $loan->schedules()->where('installment_number', $i)->first();
            $amount = $schedule ? (float) $schedule->amount_due : $installment;
            $this->repayment($loan, $amount, $disbursedAt->copy()->addWeeks($i), $i);
        }

        $loan->refreshBalance();
    }

    /**
     * Scenario 4: pending approval lifecycle example.
     */
    protected function pendingApprovalLoan(): void
    {
        $widow = $this->widow('UAT-WID-005');
        $principal = 80_000;

        $loan = $this->loan($widow, 'Tailoring equipment purchase');
        if ((float) $loan->principal_amount != $principal) {
            $loan->update([
                'principal_amount' => $principal,
                'total_payable' => $principal,
                'outstanding_balance' => $principal,
                'duration_months' => 12,
                'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
                'bank_account_id' => $this->bankAccount('1000000001')->id,
                'disbursement_bank_id' => $this->bankAccount('1000000001')->id,
                'repayment_bank_id' => $this->bankAccount('1000000002')->id,
                'status' => WidowLoanStatus::PENDING,
            ]);
        }
    }

    /**
     * Scenario 5: draft loan (not yet submitted).
     */
    protected function draftLoan(): void
    {
        $widow = $this->widow('UAT-WID-008');
        $principal = 30_000;

        $loan = $this->loan($widow, 'Provision shop restock');
        if ((float) $loan->principal_amount != $principal) {
            $loan->update([
                'principal_amount' => $principal,
                'total_payable' => $principal,
                'outstanding_balance' => $principal,
                'duration_months' => 6,
                'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
                'status' => WidowLoanStatus::DRAFT,
            ]);
        }
    }

    /**
     * Deterministically (re)generate the repayment schedule for a loan.
     */
    protected function generateSchedule(WidowLoan $loan, Carbon $startDate): void
    {
        $loan->schedules()->delete();

        $isWeekly = $loan->repayment_frequency === LoanRepaymentFrequency::WEEKLY;
        $totalIntervals = $loan->duration_months * ($isWeekly ? 4 : 1);
        $totalPayable = (float) $loan->total_payable;
        $installmentAmount = round($totalPayable / $totalIntervals, 2);
        $scheduledTotal = 0;

        for ($i = 1; $i <= $totalIntervals; $i++) {
            $dueDate = $isWeekly
                ? $startDate->copy()->addWeeks($i)
                : $startDate->copy()->addMonths($i);

            $amountDue = $i === $totalIntervals
                ? round($totalPayable - $scheduledTotal, 2)
                : $installmentAmount;

            WidowLoanSchedule::create([
                'widow_loan_id' => $loan->id,
                'installment_number' => $i,
                'amount_due' => $amountDue,
                'due_date' => $dueDate,
                'is_paid' => false,
                'status' => WidowLoanScheduleStatus::PENDING,
            ]);

            $scheduledTotal += $amountDue;
        }
    }

    /**
     * Deterministic repayment record for a loan installment.
     */
    protected function repayment(WidowLoan $loan, float $amount, Carbon $paidAt, int $installment): void
    {
        WidowLoanRepayment::firstOrCreate(
            [
                'widow_loan_id' => $loan->id,
                'notes' => 'UAT-REP-'.substr($loan->id, 0, 8).'-'.str_pad((string) $installment, 3, '0', STR_PAD_LEFT),
            ],
            [
                'bank_account_id' => $this->bankAccount('1000000002')->id,
                'receipt_number' => $this->nextReceiptNumber(),
                'amount' => $amount,
                'paid_at' => $paidAt->toDateString(),
                'payment_method' => 'cash',
            ]
        );
    }

    protected function nextReceiptNumber(): int
    {
        return (int) WidowLoanRepayment::max('receipt_number') + 1;
    }
}
