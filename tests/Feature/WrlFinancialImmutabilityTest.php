<?php

use App\Enums\WidowLoanStatus;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\WidowLoanSchedule;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\Zone;
use App\Models\Deceased;

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
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'user_id' => $this->superAdmin->id,
    ]);

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
    $this->expectExceptionMessage("Cannot modify financial term");
    
    $this->loan->update([
        'principal_amount' => 120000,
    ]);
});

test('financially active loan prevents deletion', function () {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Cannot delete a loan that has already been financially active or scheduled");
    
    $this->loan->delete();
});

test('paid schedule row prevents modification', function () {
    $schedule = $this->loan->schedules()->first();
    $schedule->update(['is_paid' => true, 'paid_at' => now()]);
    
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Cannot modify the amount or installment number of a paid schedule row");
    
    $schedule->update([
        'amount_due' => 6000,
    ]);
});

test('paid schedule row prevents deletion', function () {
    $schedule = $this->loan->schedules()->first();
    $schedule->update(['is_paid' => true, 'paid_at' => now()]);
    
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Cannot delete a paid schedule row");
    
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
    $this->expectExceptionMessage("Posted financial repayments cannot be edited");
    
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
    $this->expectExceptionMessage("Posted financial repayments cannot be deleted");
    
    $repayment->delete();
});
