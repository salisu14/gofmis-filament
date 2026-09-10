<?php

use App\Enums\Gender;
use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanStatus;
use App\Filament\Coordinator\Resources\LoanRequestResource;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\EditLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ListLoanRequests;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ViewLoanRequest;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('coordinator'));

    app(PermissionRegistrar::class)->forgetCachedPermissions();

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

    $this->coordinator->refresh();
    $this->actingAs($this->coordinator);
});

// POSITIVE CAPABILITIES
test('1. coordinator can access loan request list page', function () {
    Livewire::test(ListLoanRequests::class)
        ->assertSuccessful();
});

test('2. coordinator can access loan request create page', function () {
    Livewire::test(CreateLoanRequest::class)
        ->assertSuccessful();
});

test('3. coordinator can create DRAFT loan request for eligible own-zone widow', function () {
    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => $this->widow->id,
            'principal_amount' => 30000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Trade expansion support',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('widow_loans', [
        'widow_id' => $this->widow->id,
        'principal_amount' => 30000,
        'total_payable' => 30000,
        'total_paid' => 0,
        'outstanding_balance' => 30000,
        'fully_repaid' => false,
        'status' => WidowLoanStatus::DRAFT->value,
    ]);
});

test('4. coordinator can view own-zone loan request', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DRAFT,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->assertSuccessful();
});

test('5. coordinator can edit own-zone DRAFT loan request', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 30000.00,
        'total_payable' => 30000.00,
        'outstanding_balance' => 30000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DRAFT,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(EditLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->fillForm([
            'widow_id' => (string) $this->widow->id,
            'principal_amount' => 45000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Updated Trading Purpose',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($loan->fresh()->principal_amount)->toEqual('45000.00');
});

test('6. coordinator can submit DRAFT request for approval', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DRAFT,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->callAction('submitForApproval')
        ->assertHasNoActionErrors();

    expect($loan->fresh()->status)->toBe(WidowLoanStatus::PENDING);
    $this->assertDatabaseHas('approval_flows', [
        'model_type' => WidowLoan::class,
        'model_id' => $loan->id,
        'status' => 'pending',
    ]);
});

test('7. coordinator can see status and approval progress on loan request view', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::PENDING,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->assertSuccessful()
        ->assertSeeHtml('pending');
});

// ZONE ISOLATION & SECURITY
test('8. out-of-zone widow is absent from coordinator create form selector', function () {
    Livewire::test(CreateLoanRequest::class)
        ->assertFormFieldExists('widow_id');

    $query = LoanRequestResource::getEloquentQuery();
    expect($query->where('widow_id', $this->otherWidow->id)->exists())->toBeFalse();
});

test('9. forged submission with out-of-zone widow_id is rejected by validation', function () {
    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => $this->otherWidow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Forged request',
        ])
        ->call('create')
        ->assertHasFormErrors(['widow_id']);
});

test('10. coordinator direct access to out-of-zone loan request is denied', function () {
    $otherLoan = WidowLoan::create([
        'widow_id' => $this->otherWidow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DRAFT,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    expect(fn () => Livewire::test(ViewLoanRequest::class, ['record' => $otherLoan->getRouteKey()]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

// WIDOW ELIGIBILITY
test('11. ineligible widow is rejected by form validation', function () {
    $this->widow->update(['is_eligible' => false]);

    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => $this->widow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Ineligible request',
        ])
        ->call('create')
        ->assertHasFormErrors(['widow_id']);
});

test('12. remarried widow is rejected by form validation', function () {
    $this->widow->update(['is_married' => true]);

    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => $this->widow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Remarried request',
        ])
        ->call('create')
        ->assertHasFormErrors(['widow_id']);
});

test('13. widow with active loan is rejected from receiving new loan request', function () {
    WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DISBURSED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => $this->widow->id,
            'principal_amount' => 50000,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Duplicate active request',
        ])
        ->call('create')
        ->assertHasFormErrors(['widow_id']);
});

// PRIVILEGE BOUNDARIES
test('14. coordinator cannot approve loan', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::PENDING,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->assertActionDoesNotExist('approveLoan');
});

test('15. coordinator cannot disburse loan', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::APPROVED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->assertActionDoesNotExist('disburseLoan');
});

test('16. coordinator cannot write off loan', function () {
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DISBURSED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'purpose' => 'Trading',
    ]);

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->getRouteKey()])
        ->assertActionDoesNotExist('writeOffLoan');
});

test('17. coordinator is forbidden from Secretariat admin panel access and financial administration permissions', function () {
    $adminPanelRoles = ['super_admin', 'admin', 'auditor', 'demo_observer'];
    expect($this->coordinator->hasAnyRole($adminPanelRoles))->toBeFalse();
    expect($this->coordinator->can('approve_loans'))->toBeFalse();
    expect($this->coordinator->can('disburse_loans'))->toBeFalse();
    expect($this->coordinator->can('write_off_loans'))->toBeFalse();
});

function assertNewLoanFinancialState(WidowLoan $loan): void
{
    $loan->refresh();
    expect($loan->principal_amount)->toBe('30000.00')
        ->and($loan->total_payable)->toBe('30000.00')
        ->and($loan->total_paid)->toBe('0.00')
        ->and($loan->outstanding_balance)->toBe('30000.00')
        ->and($loan->fully_repaid)->toBeFalse()
        ->and($loan->status)->toBe(WidowLoanStatus::DRAFT)
        ->and($loan->performance_status)->toBe(WidowLoanPerformanceStatus::CURRENT)
        ->and($loan->days_past_due)->toBe(0)
        ->and($loan->disbursed_at)->toBeNull()
        ->and($loan->collected_at)->toBeNull()
        ->and($loan->schedules()->count())->toBe(0)
        ->and($loan->repayments()->count())->toBe(0);
}

test('canonical service and legacy creation action initialize principal-only financial state', function (string $path) {
    $data = new \App\Data\Loan\CreateWidowLoanData(
        widowId: $this->widow->id,
        principalAmount: 30000,
        durationMonths: 6,
        bankAccountId: $this->disbursingAccount->id,
    );
    $loan = $path === 'service'
        ? app(\App\Services\WidowLoanService::class)->createLoan($data)
        : app(\App\Actions\Loan\CreateWidowLoanAction::class)->execute($data);

    assertNewLoanFinancialState($loan);
    expect($this->disbursingAccount->fresh()->ledger_balance)->toBe('500000.00');
    $this->assertDatabaseCount('transactions', 0);
})->with(['service', 'action']);

test('creation pages ignore forged financial and workflow state', function (string $panel) {
    if ($panel === 'admin') {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }
    $page = $panel === 'admin'
        ? \App\Filament\Resources\WidowLoans\Pages\CreateWidowLoan::class
        : CreateLoanRequest::class;

    Livewire::test($page)->fillForm([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'principal_amount' => 30000,
        'duration_months' => 6,
        'repayment_frequency' => 'weekly',
        'purpose' => 'Trading capital',
    ])
        ->set('data.total_payable', 1)
        ->set('data.total_paid', 30000)
        ->set('data.outstanding_balance', 0)
        ->set('data.fully_repaid', true)
        ->set('data.performance_status', 'defaulted')
        ->set('data.days_past_due', 99)
        ->set('data.status', WidowLoanStatus::DISBURSED->value)
        ->set('data.disbursed_at', now()->toDateTimeString())
        ->set('data.collected_at', now()->toDateTimeString())
        ->call('create')->assertHasNoFormErrors();

    assertNewLoanFinancialState(WidowLoan::sole());
    $this->assertDatabaseCount('transactions', 0);
})->with(['coordinator', 'admin']);

test('creation pages reject nonpositive principal', function (string $panel, int $amount) {
    if ($panel === 'admin') {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }
    Livewire::test($panel === 'admin'
        ? \App\Filament\Resources\WidowLoans\Pages\CreateWidowLoan::class
        : CreateLoanRequest::class)
        ->fillForm([
            'widow_id' => $this->widow->id,
            'principal_amount' => $amount,
            'duration_months' => 6,
            'repayment_frequency' => 'weekly',
            'purpose' => 'Invalid application',
        ])->call('create')->assertHasFormErrors(['principal_amount']);
    $this->assertDatabaseCount('widow_loans', 0);
})->with(['coordinator', 'admin'])->with([0, -1000]);

test('canonical creation rejects nonpositive principal even without a form', function (int $amount) {
    expect(fn () => app(\App\Services\WidowLoanService::class)->createLoan(
        new \App\Data\Loan\CreateWidowLoanData(widowId: $this->widow->id, principalAmount: $amount)
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
    $this->assertDatabaseCount('widow_loans', 0);
})->with([0, -1000]);

test('canonical creation retains coordinator zone scope without a form', function () {
    expect(fn () => app(\App\Services\WidowLoanService::class)->createLoan(
        new \App\Data\Loan\CreateWidowLoanData(widowId: $this->otherWidow->id, principalAmount: 30000)
    ))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $this->assertDatabaseCount('widow_loans', 0);
});

test('coordinator-created loan preserves financial state through approval disbursement collection and first repayment', function () {
    Livewire::test(CreateLoanRequest::class)->fillForm([
        'widow_id' => $this->widow->id,
        'principal_amount' => 30000,
        'duration_months' => 6,
        'repayment_frequency' => 'monthly',
        'purpose' => 'Trading capital',
    ])->call('create')->assertHasNoFormErrors();
    $loan = WidowLoan::sole();
    assertNewLoanFinancialState($loan);
    expect($loan->canRecordRepayment())->toBeFalse();

    Livewire::test(ViewLoanRequest::class, ['record' => $loan->id])
        ->callAction('submitForApproval')->assertHasNoActionErrors();
    expect($loan->refresh()->status)->toBe(WidowLoanStatus::PENDING)
        ->and($this->disbursingAccount->fresh()->reserved_balance)->toBe('30000.00');

    $this->actingAs($this->superAdmin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $view = \App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan::class;
    Livewire::test($view, ['record' => $loan->id])
        ->callAction('approve', ['comments' => 'Approved for test business'])
        ->assertHasNoActionErrors();
    expect($loan->refresh()->status)->toBe(WidowLoanStatus::APPROVED)
        ->and($loan->collected_at)->toBeNull()
        ->and($loan->canRecordRepayment())->toBeFalse();

    Livewire::test($view, ['record' => $loan->id])
        ->callAction('disburse')->assertHasNoActionErrors();
    $loan->refresh();
    expect($loan->status)->toBe(WidowLoanStatus::DISBURSED)
        ->and($loan->principal_amount)->toBe('30000.00')
        ->and($loan->total_payable)->toBe('30000.00')
        ->and($loan->outstanding_balance)->toBe('30000.00')
        ->and($loan->canRecordRepayment())->toBeFalse()
        ->and($loan->schedules()->count())->toBe(6)
        ->and((float) $loan->schedules()->sum('amount_due'))->toBe(30000.0)
        ->and($this->disbursingAccount->fresh()->ledger_balance)->toBe('470000.00')
        ->and($this->disbursingAccount->fresh()->reserved_balance)->toBe('0.00');
    $this->assertDatabaseHas('transactions', [
        'transactionable_id' => $loan->id,
        'type' => 'loan_disbursement',
        'amount' => 30000,
        'bank_account_id' => $this->disbursingAccount->id,
    ]);
    $schedules = $loan->schedules()->orderBy('id')->get()->toArray();

    Livewire::test($view, ['record' => $loan->id])
        ->callAction('markCollected', ['collector_name' => 'Disposable Test Collector'])
        ->assertHasNoActionErrors();
    $loan->refresh();
    expect($loan->collected_at)->not->toBeNull()
        ->and($loan->principal_amount)->toBe('30000.00')
        ->and($loan->total_payable)->toBe('30000.00')
        ->and($loan->outstanding_balance)->toBe('30000.00')
        ->and($loan->canRecordRepayment())->toBeTrue()
        ->and($loan->schedules()->orderBy('id')->get()->toArray())->toBe($schedules);

    $repaymentBank = BankAccount::create([
        'account_name' => 'Disposable Repayment Fund',
        'account_number' => '2222222222',
        'bank_name' => 'Test Bank',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
        'parent_bank_account_id' => $this->mainAccount->id,
        'user_id' => $this->admin->id,
    ]);
    $repayment = app(\App\Services\WidowLoanService::class)->recordRepayment(
        new \App\Data\Loan\RecordWidowLoanRepaymentData(
            widowLoanId: $loan->id,
            amount: 5000,
            paidAt: now()->toDateString(),
            paymentMethod: 'cash',
            bankAccountId: $repaymentBank->id,
        )
    );
    $loan->refresh();
    expect($loan->total_paid)->toBe('5000.00')
        ->and($loan->outstanding_balance)->toBe('25000.00')
        ->and($loan->total_payable)->toBe('30000.00')
        ->and($loan->fully_repaid)->toBeFalse()
        ->and($loan->canRecordRepayment())->toBeTrue()
        ->and($repaymentBank->fresh()->ledger_balance)->toBe('5000.00')
        ->and($this->disbursingAccount->fresh()->ledger_balance)->toBe('470000.00')
        ->and($this->mainAccount->fresh()->ledger_balance)->toBe('1000000.00');
    $this->assertDatabaseHas('transactions', [
        'transactionable_id' => $repayment->id,
        'type' => 'loan_repayment',
        'amount' => 5000,
        'bank_account_id' => $repaymentBank->id,
    ]);
    $this->assertDatabaseCount('transactions', 2);
});

test('canonical service rejects ineligible and remarried widows', function (string $field, bool $value) {
    $this->widow->update([$field => $value]);
    expect(fn () => app(\App\Services\WidowLoanService::class)->createLoan(
        new \App\Data\Loan\CreateWidowLoanData(widowId: $this->widow->id, principalAmount: 30000)
    ))->toThrow(\RuntimeException::class, 'This widow is not eligible');
    $this->assertDatabaseCount('widow_loans', 0);
})->with([['is_eligible', false], ['is_married', true]]);

test('legacy creation action retains dedicated account and available funds checks', function (string $account) {
    $bank = $account === 'general' ? $this->mainAccount : $this->disbursingAccount;
    $exception = $account === 'general'
        ? \Illuminate\Validation\ValidationException::class
        : \App\Exceptions\InsufficientBankBalanceException::class;
    expect(fn () => app(\App\Actions\Loan\CreateWidowLoanAction::class)->execute(
        new \App\Data\Loan\CreateWidowLoanData(
            widowId: $this->widow->id,
            principalAmount: 600000,
            bankAccountId: $bank->id,
        )
    ))->toThrow($exception);
    $this->assertDatabaseCount('widow_loans', 0);
    $this->assertDatabaseCount('transactions', 0);
})->with(['general', 'insufficient']);
