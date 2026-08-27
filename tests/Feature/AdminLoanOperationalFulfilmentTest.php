<?php

use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Enums\Gender;
use App\Enums\WidowLoanStatus;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ViewLoanRequest;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use App\Services\WidowLoanService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Khadijah',
        'last_name' => 'Ibrahim',
        'nin' => '12345678910',
        'reg_no' => 'WID-00100',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->mainAccount = BankAccount::create([
        'account_name' => 'Main Operating Account',
        'account_number' => '0000000000',
        'bank_name' => 'First Bank',
        'usage' => 'general',
        'ledger_balance' => 1000000.00,
        'reserved_balance' => 0.00,
        'user_id' => $this->admin->id,
    ]);

    $this->disbursingAccount = BankAccount::create([
        'account_name' => 'Widow Loan Disbursement Fund',
        'account_number' => '1111111111',
        'bank_name' => 'First Bank',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
        'reserved_balance' => 0.00,
        'parent_bank_account_id' => $this->mainAccount->id,
        'user_id' => $this->admin->id,
    ]);
    $this->disbursingAccount->update(['ledger_balance' => 500000.00]);

    $this->repaymentAccount = BankAccount::create([
        'account_name' => 'Widow Loan Repayment Fund',
        'account_number' => '2222222222',
        'bank_name' => 'First Bank',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
        'ledger_balance' => 0.00,
        'reserved_balance' => 0.00,
        'parent_bank_account_id' => $this->mainAccount->id,
        'user_id' => $this->admin->id,
    ]);

    $this->loanService = app(WidowLoanService::class);
});

test('1. end-to-end loan approval -> disbursement -> collection -> repayment -> closure lifecycle', function () {
    // 1. Create Draft Loan by Coordinator
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'repayment_bank_id' => $this->repaymentAccount->id,
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'duration_months' => 2,
        'repayment_frequency' => 'monthly',
        'purpose' => 'Micro business capital',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 10000,
    ]);

    expect($loan->status)->toBe(WidowLoanStatus::DRAFT);

    // 2. Submit for Approval via canonical service
    $this->loanService->submitForApproval($loan, [['role' => 'super_admin']]);
    expect($loan->fresh()->status)->toBe(WidowLoanStatus::PENDING);

    // 3. Admin Approval does NOT disburse or fulfill loan directly
    $loan->update(['status' => WidowLoanStatus::APPROVED]);
    expect($loan->fresh()->status)->toBe(WidowLoanStatus::APPROVED);
    expect($loan->fresh()->disbursed_at)->toBeNull();

    // 4. Admin Disbursement triggers canonical service disburseLoan
    $this->loanService->disburseLoan($loan);
    $loan = $loan->fresh();

    expect($loan->status)->toBe(WidowLoanStatus::DISBURSED);
    expect($loan->disbursed_at)->not->toBeNull();
    expect($loan->schedules()->count())->toBe(2);

    // Financial movement audit for disbursement
    $this->assertDatabaseHas('transactions', [
        'bank_account_id' => $this->disbursingAccount->id,
        'transactionable_type' => WidowLoan::class,
        'transactionable_id' => $loan->id,
        'type' => 'loan_disbursement',
        'amount' => 10000.00,
    ]);

    // 5. Widow Collection Confirmation
    $this->loanService->collectLoan($loan, $this->coordinator->id, 'Khadijah Ibrahim');
    expect($loan->fresh()->collected_at)->not->toBeNull();

    // 6. Partial Repayment (₦5,000 out of ₦10,000)
    $repaymentData1 = new RecordWidowLoanRepaymentData(
        widowLoanId: $loan->id,
        amount: 5000,
        paidAt: now()->toDateString(),
        paymentMethod: 'cash',
        bankAccountId: $this->repaymentAccount->id,
        notes: 'First installment payment'
    );
    $this->loanService->recordRepayment($repaymentData1);
    $loan = $loan->fresh();

    expect((float) $loan->total_paid)->toEqual(5000.00);
    expect((float) $loan->outstanding_balance)->toEqual(5000.00);
    expect($loan->status)->toBe(WidowLoanStatus::DISBURSED); // Still active/disbursed

    // 7. Final Repayment (Remaining ₦5,000) -> Transitions to COMPLETED (Terminal State)
    $repaymentData2 = new RecordWidowLoanRepaymentData(
        widowLoanId: $loan->id,
        amount: 5000,
        paidAt: now()->toDateString(),
        paymentMethod: 'cash',
        bankAccountId: $this->repaymentAccount->id,
        notes: 'Final installment payment'
    );
    $this->loanService->recordRepayment($repaymentData2);
    $loan = $loan->fresh();

    expect((float) $loan->total_paid)->toEqual(10000.00);
    expect((float) $loan->outstanding_balance)->toEqual(0.00);
    expect($loan->status)->toBe(WidowLoanStatus::COMPLETED);

    // 8. Coordinator visibility on View Loan Request page
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));
    $this->actingAs($this->coordinator);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Khadijah');
});
