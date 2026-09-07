<?php

use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->zone = Zone::create(['name' => 'Test Zone']);

    $this->deceased = Deceased::factory()->create([
        'zone_id' => $this->zone->id,
    ]);

    $this->widow = Widow::create([
        'first_name' => 'Test',
        'last_name' => 'Widow',
        'nin' => '98765432101',
        'reg_no' => 'WID-11111',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $this->deceased->id,
        'full_name' => 'Test Widow',
        'child_sequence' => 1,
    ]);

    $disbursementBank = BankAccount::create([
        'account_name' => 'Disb Account',
        'account_number' => '1234567890',
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'user_id' => $this->superAdmin->id,
    ]);

    $repaymentBank = BankAccount::create([
        'account_name' => 'Repay Account',
        'account_number' => '1234567891',
        'parent_bank_account_id' => $disbursementBank->id,
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
        'user_id' => $this->superAdmin->id,
    ]);
    $repaymentBank->update(['ledger_balance' => 500000.00]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'status' => WidowLoanStatus::DISBURSED,
        'principal_amount' => 100000,
        'total_payable' => 100000,
        'duration_months' => 5,
        'repayment_frequency' => \App\Enums\LoanRepaymentFrequency::WEEKLY,
        'disbursement_bank_id' => $disbursementBank->id,
        'repayment_bank_id' => $repaymentBank->id,
    ]);

    $this->loan->generateLedger();
});

test('financially active loan prevents mutation of financial fields', function () {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot modify financial term');

    $this->loan->update([
        'principal_amount' => 120000,
    ]);
});

test('financially active loan prevents deletion', function () {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot delete a loan that has already been financially active or scheduled');

    $this->loan->delete();
});

test('paid schedule row prevents modification', function () {
    $schedule = $this->loan->schedules()->first();
    $schedule->update(['is_paid' => true, 'paid_at' => now()]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot modify the amount or installment number of a paid schedule row');

    $schedule->update([
        'amount_due' => 6000,
    ]);
});

test('paid schedule row prevents deletion', function () {
    $schedule = $this->loan->schedules()->first();
    $schedule->update(['is_paid' => true, 'paid_at' => now()]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot delete a paid schedule row');

    $schedule->delete();
});

test('posted repayment prevents manual update', function () {
    $repayment = WidowLoanRepayment::create([
        'widow_loan_id' => $this->loan->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'paid_at' => now()->startOfDay(),
        'receipt_number' => 1001,
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Posted financial repayments cannot be edited');

    $repayment->update([
        'amount' => 6000,
    ]);
});

test('posted repayment prevents manual deletion', function () {
    $repayment = WidowLoanRepayment::create([
        'widow_loan_id' => $this->loan->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'paid_at' => now()->startOfDay(),
        'receipt_number' => 1002,
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Posted financial repayments cannot be deleted');

    $repayment->delete();
});

test('attachTransactionReference is allowed, idempotent, and narrowly scoped', function () {
    $repayment = WidowLoanRepayment::create([
        'widow_loan_id' => $this->loan->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'paid_at' => now()->startOfDay(),
        'receipt_number' => 1003,
    ]);

    $txn = \App\Models\Transaction::create([
        'bank_account_id' => $repayment->fresh()->bank_account_id ?? ($this->loan->repayment_bank_id),
        'amount' => 5000,
        'type' => 'loan_repayment',
        'date' => now(),
        'reference' => 'REP-TEST-1003',
        'is_system' => true,
    ]);

    // Sanctioned internal mutation: attaches the transaction FK only.
    $repayment->attachTransactionReference($txn->id);
    expect($repayment->fresh()->transaction_id)->toBe($txn->id);

    // Idempotent for the same reference.
    $repayment->attachTransactionReference($txn->id);
    expect($repayment->fresh()->transaction_id)->toBe($txn->id);

    // Refuses to overwrite a different existing reference.
    $otherTxn = \App\Models\Transaction::create([
        'bank_account_id' => $repayment->fresh()->bank_account_id ?? ($this->loan->repayment_bank_id),
        'amount' => 5000,
        'type' => 'loan_repayment',
        'date' => now(),
        'reference' => 'REP-TEST-1003B',
        'is_system' => true,
    ]);
    $repayment->attachTransactionReference($txn->id); // ensure consistent state
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot overwrite an existing transaction reference');
    $repayment->attachTransactionReference($otherTxn->id);
});

test('attachTransactionReference cannot change financial facts', function () {
    $repayment = WidowLoanRepayment::create([
        'widow_loan_id' => $this->loan->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'paid_at' => now()->startOfDay(),
        'receipt_number' => 1004,
    ]);

    $txn = \App\Models\Transaction::create([
        'bank_account_id' => $repayment->fresh()->bank_account_id ?? ($this->loan->repayment_bank_id),
        'amount' => 5000,
        'type' => 'loan_repayment',
        'date' => now(),
        'reference' => 'REP-TEST-1004',
        'is_system' => true,
    ]);

    $repayment->attachTransactionReference($txn->id);

    $fresh = $repayment->fresh();

    expect((float) $fresh->amount)->toBe(5000.0)
        ->and((int) $fresh->receipt_number)->toBe(1004)
        ->and($fresh->payment_method)->toBe('cash');
});

function sameDayRepayment(WidowLoan $loan, float $amount, int $receipt, string $createdAt): WidowLoanRepayment
{
    return WidowLoanRepayment::withoutEvents(fn () => WidowLoanRepayment::forceCreate([
        'widow_loan_id' => $loan->id,
        'amount' => $amount,
        'payment_method' => 'cash',
        'paid_at' => '2026-01-06',
        'receipt_number' => $receipt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]));
}

test('historical balance is deterministic for same-paid_at repayments regardless of created_at ties', function () {
    // Three repayments, ALL on the same paid_at date AND same created_at second
    // (a realistic batch/weekly-collection scenario). Only receipt_number ordering
    // can make history deterministic here.
    $createdAt = '2026-01-06 10:00:00';

    sameDayRepayment($this->loan, 1000, 2001, $createdAt);
    sameDayRepayment($this->loan, 2000, 2002, $createdAt);
    sameDayRepayment($this->loan, 3000, 2003, $createdAt);

    $r1 = $this->loan->repayments()->where('receipt_number', 2001)->first();
    $r2 = $this->loan->repayments()->where('receipt_number', 2002)->first();
    $r3 = $this->loan->repayments()->where('receipt_number', 2003)->first();

    // Cumulative paid strictly by (paid_at, receipt_number).
    expect($r1->total_paid_up_to_this)->toBe(1000.0)
        ->and($r2->total_paid_up_to_this)->toBe(3000.0)
        ->and($r3->total_paid_up_to_this)->toBe(6000.0);

    // Historical balance after each repayment.
    expect($r1->balance_after)->toBe(99000.0)
        ->and($r2->balance_after)->toBe(97000.0)
        ->and($r3->balance_after)->toBe(94000.0);
});

test('deterministic historical receipt does not disturb current loan totals', function () {
    sameDayRepayment($this->loan, 1000, 3001, '2026-01-06 10:00:00');
    sameDayRepayment($this->loan, 2000, 3002, '2026-01-06 10:00:00');

    $loanTotalPaid = (float) $this->loan->repayments()->sum('amount');

    expect($loanTotalPaid)->toBe(3000.0);
});
