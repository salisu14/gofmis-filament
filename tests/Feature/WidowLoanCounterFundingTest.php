<?php

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanStatus;
use App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan;
use App\Filament\Resources\WidowLoans\RelationManagers\CounterFundingRelationManager;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanCounterFunding;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true, 'status' => \App\Enums\UserStatus::ACTIVE]);
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'Zone CF']);
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Counter', 'last_name' => 'Funding',
        'full_name' => 'Counter Funding', 'reg_no' => 'WID-CF-001',
        'nin' => '45454545454', 'is_eligible' => true, 'is_married' => false, 'child_sequence' => 1,
    ]);

    $bank = BankAccount::create([
        'account_name' => 'CF Bank', 'account_number' => '2020202020',
        'opening_balance' => 1000000.00, 'ledger_balance' => 1000000.00, 'user_id' => $this->admin->id,
    ]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'status' => WidowLoanStatus::DISBURSED,
        'principal_amount' => 60000.00,
        'total_payable' => 60000.00,
        'outstanding_balance' => 60000.00,
        'total_paid' => 0.00,
        'duration_months' => 5,
        'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
        'bank_account_id' => $bank->id,
        'disbursement_bank_id' => $bank->id,
        'repayment_bank_id' => $bank->id,
    ]);
});

test('counter funding is derived from the ledger and reduces outstanding', function () {
    // Widow repays NGN 3,750.
    WidowLoanRepayment::withoutEvents(fn () => WidowLoanRepayment::forceCreate([
        'widow_loan_id' => $this->loan->id,
        'amount' => 3750.00,
        'paid_at' => now()->toDateString(),
        'payment_method' => 'cash',
        'receipt_number' => 1001,
    ]));

    // Foundation counter funds NGN 10,000.
    $this->loan->counterFundings()->create([
        'amount' => 10000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 60000.00,
        'balance_after' => 50000.00,
        'notes' => 'Hardship counter funding',
    ]);

    $this->loan->refreshBalance();
    $fresh = $this->loan->fresh();

    expect((float) $fresh->outstanding_balance)->toBe(46250.0); // 60000 - 3750 - 10000
});

test('counter funding remains separately reportable and is neither repayment nor write-off', function () {
    $this->loan->counterFundings()->create([
        'amount' => 10000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 60000.00,
        'balance_after' => 50000.00,
    ]);

    $this->loan->refreshBalance();
    $fresh = $this->loan->fresh();

    // Counter funding is NOT counted as a widow repayment...
    expect((float) $fresh->total_paid)->toBe(0.0);

    // ...and NOT as a write-off.
    expect((float) $fresh->amount_written_off)->toBe(0.0)
        ->and((float) $fresh->outstanding_balance)->toBe(50000.0);

    // Ledger rows exist and are separable.
    expect(WidowLoanCounterFunding::where('widow_loan_id', $this->loan->id)->count())->toBe(1);
});

test('multiple counter-funding transactions aggregate correctly from the ledger', function () {
    // 3,750 widow repayment.
    WidowLoanRepayment::withoutEvents(fn () => WidowLoanRepayment::forceCreate([
        'widow_loan_id' => $this->loan->id,
        'amount' => 3750.00,
        'paid_at' => now()->toDateString(),
        'payment_method' => 'cash',
        'receipt_number' => 2001,
    ]));

    // 10,000 then 5,000 counter funding.
    $this->loan->counterFundings()->create([
        'amount' => 10000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 60000.00, 'balance_after' => 50000.00,
    ]);
    $this->loan->counterFundings()->create([
        'amount' => 5000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 50000.00, 'balance_after' => 45000.00,
    ]);

    $this->loan->refreshBalance();
    $fresh = $this->loan->fresh();

    expect((float) WidowLoanCounterFunding::where('widow_loan_id', $this->loan->id)->sum('amount'))->toBe(15000.0)
        ->and((float) $fresh->outstanding_balance)->toBe(41250.0) // 60000 - 3750 - 10000 - 5000
        ->and((float) $fresh->total_paid)->toBe(3750.0);
});

test('multi-entry financial calculation produces exact required totals', function () {
    $exactLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'status' => WidowLoanStatus::DISBURSED,
        'principal_amount' => 40000.00,
        'total_payable' => 40000.00,
        'outstanding_balance' => 40000.00,
        'total_paid' => 0.00,
        'duration_months' => 6,
        'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
    ]);

    // Widow repayment: 20000.04
    WidowLoanRepayment::withoutEvents(fn () => WidowLoanRepayment::forceCreate([
        'widow_loan_id' => $exactLoan->id,
        'amount' => 20000.04,
        'paid_at' => now()->toDateString(),
        'payment_method' => 'cash',
        'receipt_number' => 3001,
    ]));

    // Counter funding 1: 5000
    $exactLoan->counterFundings()->create([
        'amount' => 5000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 40000.00,
        'balance_after' => 35000.00,
        'notes' => 'Phase 1 Relief',
    ]);

    // Counter funding 2: 5000
    $exactLoan->counterFundings()->create([
        'amount' => 5000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 35000.00,
        'balance_after' => 30000.00,
        'notes' => 'Phase 2 Relief',
    ]);

    $exactLoan->refreshBalance();
    $fresh = $exactLoan->fresh();

    expect((float) $fresh->total_paid)->toBe(20000.04)
        ->and((float) $fresh->total_counter_funded)->toBe(10000.0)
        ->and((float) $fresh->outstanding_balance)->toBe(9999.96);
});

test('widow loan view page displays total counter funded summary and counter funding relation manager', function () {
    $this->actingAs($this->admin);

    $this->loan->counterFundings()->create([
        'amount' => 5000.00,
        'recorded_by' => $this->admin->id,
        'transaction_date' => now()->toDateString(),
        'balance_before' => 60000.00,
        'balance_after' => 55000.00,
        'notes' => 'WRL Counter Grant',
    ]);

    $this->loan->refreshBalance();

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Total Counter Funded');

    Livewire::test(CounterFundingRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords($this->loan->counterFundings)
        ->assertSee('WRL Counter Grant')
        ->assertSee($this->admin->name);
});

test('unauthorized users cannot view widow loan view page or counter funding history', function () {
    $unauthorizedUser = User::factory()->create(['is_active' => true]);

    $this->actingAs($unauthorizedUser);

    expect(fn () => Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
