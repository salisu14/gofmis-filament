<?php

use App\Enums\Gender;
use App\Enums\WidowLoanStatus;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ListLoanRequests;
use App\Filament\Resources\WidowLoans\Pages\ListWidowLoans;
use App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->zone = Zone::create(['name' => 'North Zone', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'South Zone', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $this->otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);

    $this->widow = Widow::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Fatimah',
        'last_name' => 'Aliyu',
        'nin' => '12345678901',
        'reg_no' => 'WID-00001',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->otherWidow = Widow::create([
        'deceased_id' => $this->otherDeceased->id,
        'first_name' => 'Halima',
        'last_name' => 'Usman',
        'nin' => '12345678902',
        'reg_no' => 'WID-00002',
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

    $this->actingAs($this->coordinator);
});

test('1. coordinator can render Loan Request create page', function () {
    Livewire::test(CreateLoanRequest::class)
        ->assertSuccessful();
});

test('2. coordinator can create valid loan request for own-zone widow', function () {
    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => (string) $this->widow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Small trading business capital',
            'notes' => 'Verified beneficiary by coordinator',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('widow_loans', [
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Small trading business capital',
        'status' => WidowLoanStatus::DRAFT->value,
    ]);
});

test('3. coordinator cannot select other-zone widow for loan request', function () {
    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => (string) $this->otherWidow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Cross-zone loan request attempt',
        ])
        ->call('create')
        ->assertHasFormErrors(['widow_id']);
});

test('4. coordinator can submit draft loan request creating pending approval flow', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan)
        ->assertHasNoTableActionErrors();

    $freshLoan = $loan->fresh();
    expect($freshLoan->status)->toBe(WidowLoanStatus::PENDING)
        ->and($freshLoan->approvalFlow)->not->toBeNull()
        ->and($freshLoan->approvalFlow->status)->toBe('pending')
        ->and($freshLoan->isAwaitingApproval())->toBeTrue();
});

test('5 & 6 & 7 & 8. Admin table and View page render Approve and Reject actions for pending coordinator loan', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ListWidowLoans::class)
        ->assertTableActionExists('approve', record: $loan->fresh())
        ->assertTableActionExists('reject', record: $loan->fresh());

    Livewire::test(ViewWidowLoan::class, ['record' => $loan->getRouteKey()])
        ->assertActionExists('approve')
        ->assertActionExists('reject');
});

test('9. Admin can approve coordinator-created pending loan', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->callTableAction('approve', $loan->fresh(), ['comments' => 'Verified and approved'])
        ->assertHasNoTableActionErrors();

    expect($loan->fresh()->status)->toBe(WidowLoanStatus::APPROVED);
});

test('10. Admin can reject coordinator-created pending loan with reason', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ListWidowLoans::class)
        ->callTableAction('reject', $loan->fresh(), [
            'reason' => 'Ineligible business model submitted',
            'comments' => 'Please review purpose details',
        ])
        ->assertHasNoTableActionErrors();

    $freshLoan = $loan->fresh();
    expect($freshLoan->status)->toBe(WidowLoanStatus::REJECTED)
        ->and($freshLoan->reject_reason)->toBe('Ineligible business model submitted');
});

test('11. rejected loan cannot be approved afterward', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::REJECTED,
        'reject_reason' => 'Rejected by admin',
        'outstanding_balance' => 0,
    ]);

    expect($loan->isAwaitingApproval())->toBeFalse();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ListWidowLoans::class)
        ->assertTableActionHidden('approve', record: $loan);
});

test('12. approved/disbursed loan cannot be rejected', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
        'outstanding_balance' => 50000,
    ]);

    expect($loan->isAwaitingApproval())->toBeFalse();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(ListWidowLoans::class)
        ->assertTableActionHidden('reject', record: $loan);
});

test('13. forged status-only pending record without approval flow can be re-submitted cleanly', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Status only pending',
        'status' => WidowLoanStatus::PENDING,
        'outstanding_balance' => 0,
    ]);

    expect($loan->approvalFlow)->toBeNull()
        ->and($loan->canSubmitForApproval())->toBeTrue();

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan)
        ->assertHasNoTableActionErrors();

    expect($loan->fresh()->approvalFlow)->not->toBeNull()
        ->and($loan->fresh()->isAwaitingApproval())->toBeTrue();
});

test('14. insufficient funds produce controlled notification without 500 error', function () {
    $this->disbursingAccount->update(['ledger_balance' => 0.00]);

    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan)
        ->assertHasNoTableActionErrors();

    // Loan remains in draft status due to insufficient balance notification
    expect($loan->fresh()->status)->toBe(WidowLoanStatus::DRAFT);
});

test('15. coordinator cannot approve or reject own loan', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    Livewire::test(ListLoanRequests::class)
        ->callTableAction('submitForApproval', $loan);

    // Coordinator table does not expose approve or reject actions
    Livewire::test(ListLoanRequests::class)
        ->assertTableActionDoesNotExist('approve')
        ->assertTableActionDoesNotExist('reject');
});

test('16. cross-zone authorization isolation remains intact', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 50000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading business',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 0,
    ]);

    $this->actingAs($this->otherCoordinator);

    Livewire::test(ListLoanRequests::class)
        ->assertDontSee($this->widow->full_name);
});
