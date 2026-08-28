<?php

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanStatus;
use App\Filament\Resources\WidowLoanRepayments\Pages\CreateWidowLoanRepayment;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->mainAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'WRL',
        'account_number' => '1230000178',
        'opening_balance' => 5000000,
        'ledger_balance' => 5000000,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $this->zone = Zone::create(['name' => 'Test Zone']);
    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Hauwa',
        'last_name' => 'Ibrahim',
        'full_name' => 'Hauwa Ibrahim',
        'reg_no' => 'WID-TEST-001',
        'nin' => '11111111111',
        'is_eligible' => true,
        'is_married' => false,
        'child_sequence' => 1,
    ]);

    $this->repaymentAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'parent_bank_account_id' => $this->mainAccount->id,
        'account_name' => 'WRL Repayment Account',
        'account_number' => '1000000002',
        'opening_balance' => 0,
        'ledger_balance' => 0,
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
    ]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'purpose' => 'Business Support',
        'status' => WidowLoanStatus::DISBURSED,
        'collected_at' => now()->subDays(10),
        'principal_amount' => 100000.00,
        'total_payable' => 100000.00,
        'outstanding_balance' => 100000.00,
        'total_paid' => 0.00,
        'duration_months' => 6,
        'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
        'fully_repaid' => false,
        'repayment_bank_id' => $this->repaymentAccount->id,
    ]);
});

test('direct route renders component and preselects WRL Repayment Account 1000000002 on loan selection', function () {
    Livewire::test(CreateWidowLoanRepayment::class)
        ->assertFormFieldExists('widow_loan_id')
        ->assertFormFieldExists('bank_account_id')
        ->set('data.widow_loan_id', $this->loan->id)
        ->assertFormSet([
            'widow_loan_id' => $this->loan->id,
            'bank_account_id' => $this->repaymentAccount->id,
        ]);
});

test('shortcut route renders component and preselects WRL Repayment Account 1000000002 automatically', function () {
    Livewire::withQueryParams(['widow_loan_id' => $this->loan->id])
        ->test(CreateWidowLoanRepayment::class)
        ->assertFormSet([
            'widow_loan_id' => $this->loan->id,
            'bank_account_id' => $this->repaymentAccount->id,
        ]);
});

test('single eligible repayment account is auto-selected under both paths', function () {
    // Shortcut
    Livewire::withQueryParams(['widow_loan_id' => $this->loan->id])
        ->test(CreateWidowLoanRepayment::class)
        ->assertFormSet(['bank_account_id' => $this->repaymentAccount->id]);

    // Direct
    Livewire::test(CreateWidowLoanRepayment::class)
        ->set('data.widow_loan_id', $this->loan->id)
        ->assertFormSet(['bank_account_id' => $this->repaymentAccount->id]);
});

test('multiple eligible repayment accounts leave all valid accounts selectable', function () {
    $acc2 = BankAccount::create([
        'user_id' => $this->admin->id,
        'parent_bank_account_id' => $this->mainAccount->id,
        'account_name' => 'Repayment Bank Beta',
        'account_number' => '9990003332',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
    ]);

    // Test shortcut path with loan without explicit repayment_bank_id
    $loanWithoutSpecificBank = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'purpose' => 'General Loan Purpose',
        'status' => WidowLoanStatus::DISBURSED,
        'collected_at' => now()->subDays(10),
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'duration_months' => 6,
        'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
        'fully_repaid' => false,
    ]);

    Livewire::withQueryParams(['widow_loan_id' => $loanWithoutSpecificBank->id])
        ->test(CreateWidowLoanRepayment::class)
        ->assertFormSet(['widow_loan_id' => $loanWithoutSpecificBank->id]);

    // Both accounts exist in canonical query
    $options = BankAccount::query()
        ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
        ->pluck('id')
        ->toArray();

    expect($options)->toContain($this->repaymentAccount->id)->toContain($acc2->id);

    // Create a loan specifying repayment_bank_id to test preselection
    $loanWithSpecificBank = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'purpose' => 'Business Support Beta',
        'status' => WidowLoanStatus::DISBURSED,
        'collected_at' => now()->subDays(10),
        'principal_amount' => 100000.00,
        'total_payable' => 100000.00,
        'outstanding_balance' => 100000.00,
        'total_paid' => 0.00,
        'duration_months' => 6,
        'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
        'fully_repaid' => false,
        'repayment_bank_id' => $acc2->id,
    ]);

    Livewire::withQueryParams(['widow_loan_id' => $loanWithSpecificBank->id])
        ->test(CreateWidowLoanRepayment::class)
        ->assertFormSet(['bank_account_id' => $acc2->id]);
});

test('ineligible non-repayment accounts are excluded from options', function () {
    $imprestAcc = BankAccount::create([
        'user_id' => $this->admin->id,
        'parent_bank_account_id' => $this->mainAccount->id,
        'account_name' => 'Imprest Account',
        'account_number' => '9990004443',
        'usage' => BankAccount::USAGE_IMPREST,
    ]);

    $options = BankAccount::query()
        ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
        ->pluck('id')
        ->toArray();

    expect($options)
        ->toContain($this->repaymentAccount->id)
        ->not->toContain($this->mainAccount->id)
        ->not->toContain($imprestAcc->id);
});

test('submitting repayment via shortcut path successfully records payment and credits bank account', function () {
    Livewire::withQueryParams(['widow_loan_id' => $this->loan->id])
        ->test(CreateWidowLoanRepayment::class)
        ->fillForm([
            'amount' => 10000,
            'paid_at' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->loan->refresh();
    $this->repaymentAccount->refresh();

    expect((float) $this->loan->outstanding_balance)->toBe(90000.0)
        ->and((float) $this->repaymentAccount->ledger_balance)->toBe(10000.0);
});
