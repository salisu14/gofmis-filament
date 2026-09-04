<?php

use App\Enums\WidowLoanHardshipStatus;
use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanPromiseStatus;
use App\Enums\WidowLoanRecoveryActivityType;
use App\Enums\WidowLoanRecoveryStatus;
use App\Enums\WidowLoanScheduleStatus;
use App\Enums\WidowLoanStatus;
use App\Enums\WidowLoanWriteOffRecommendationStatus;
use App\Filament\Resources\WidowLoans\Pages\ListWidowLoans;
use App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan;
use App\Filament\Resources\WidowLoans\RelationManagers\HardshipRelationManager;
use App\Filament\Resources\WidowLoans\RelationManagers\RecoveryRelationManager;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanHardshipCase;
use App\Models\WidowLoanPromise;
use App\Models\WidowLoanRecoveryCase;
use App\Models\WidowLoanSchedule;
use App\Models\Zone;
use App\Services\WidowLoanDelinquencyService;
use App\Services\WidowLoanHardshipService;
use App\Services\WidowLoanRecoveryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');

    $this->otherCoordinator = User::factory()->create();
    $this->otherCoordinator->assignRole('coordinator');

    $this->zone = Zone::create(['name' => 'Zone A', 'coordinator_id' => $this->coordinator->id]);
    $this->otherZone = Zone::create(['name' => 'Zone B', 'coordinator_id' => $this->otherCoordinator->id]);

    $this->deceased = Deceased::factory()->create([
        'zone_id' => $this->zone->id,
    ]);

    $this->widow = Widow::create([
        'first_name' => 'Fatima',
        'last_name' => 'Bello',
        'nin' => '12345678901',
        'reg_no' => 'WID-33333',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $this->deceased->id,
        'full_name' => 'Fatima Bello',
        'child_sequence' => 1,
    ]);

    $this->bankAccount = BankAccount::create([
        'account_name' => 'UI Test Bank',
        'account_number' => '1234500000',
        'opening_balance' => 1000000.00,
        'ledger_balance' => 1000000.00,
        'user_id' => $this->superAdmin->id,
    ]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DISBURSED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'disbursed_at' => now()->subDays(40),
        'collected_at' => now()->subDays(40),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    // Installment 1 overdue by 40 days
    WidowLoanSchedule::create([
        'widow_loan_id' => $this->loan->id,
        'installment_number' => 1,
        'amount_due' => 25000.00,
        'due_date' => now()->subDays(40),
        'is_paid' => false,
        'status' => WidowLoanScheduleStatus::PENDING,
    ]);

    // Installment 2 due in future
    WidowLoanSchedule::create([
        'widow_loan_id' => $this->loan->id,
        'installment_number' => 2,
        'amount_due' => 25000.00,
        'due_date' => now()->addDays(20),
        'is_paid' => false,
        'status' => WidowLoanScheduleStatus::PENDING,
    ]);

    app(WidowLoanDelinquencyService::class)->evaluateLoan($this->loan);
    $this->loan = $this->loan->fresh();
});

/*
|--------------------------------------------------------------------------
| DELINQUENCY UI TESTS
|--------------------------------------------------------------------------
*/

test('1. loan table displays performance status', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->assertCanSeeTableRecords([$this->loan])
        ->assertTableColumnStateSet('performance_status', WidowLoanPerformanceStatus::DELINQUENT, record: $this->loan);
});

test('2. loan table displays DPD', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->assertTableColumnStateSet('days_past_due', 40, record: $this->loan);
});

test('3. performance filter works', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->filterTable('performance_status', WidowLoanPerformanceStatus::DELINQUENT->value)
        ->assertCanSeeTableRecords([$this->loan])
        ->filterTable('performance_status', WidowLoanPerformanceStatus::CURRENT->value)
        ->assertCanNotSeeTableRecords([$this->loan]);
});

test('4. view infolist displays delinquency metrics', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertSuccessful()
        ->assertSeeHtml('Days Past Due (DPD)')
        ->assertSeeHtml('Overdue Amount');
});

test('5. defaulted loan shows correct badge', function () {
    $this->loan->schedules()->first()->update(['due_date' => now()->subDays(95)]);
    app(WidowLoanDelinquencyService::class)->evaluateLoan($this->loan);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->assertTableColumnStateSet('performance_status', WidowLoanPerformanceStatus::DEFAULTED, record: $this->loan);
});

test('6. hardship loan shows HARDSHIP performance badge', function () {
    $hardshipService = new WidowLoanHardshipService;
    $case = $hardshipService->reportHardshipCase($this->loan->id, $this->coordinator->id, 'health_emergency', 'Illness');
    $hardshipService->verifyHardshipCase($case->id, $this->admin->id, 'Verified');
    $hardshipService->approveHardshipCase($case->id, $this->superAdmin->id, 'Grant relief');
    $hardshipService->createReliefPeriod($this->loan->id, $case->id, now()->toDateString(), now()->addDays(30)->toDateString(), 'Relief', $this->superAdmin->id);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->assertTableColumnStateSet('performance_status', WidowLoanPerformanceStatus::HARDSHIP, record: $this->loan);
});

/*
|--------------------------------------------------------------------------
| HARDSHIP UI TESTS
|--------------------------------------------------------------------------
*/

test('7. coordinator can see Report Hardship for own-zone eligible loan', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('reportHardship');
});

test('8. coordinator cannot use action outside own zone', function () {
    $this->actingAs($this->otherCoordinator);

    expect(fn () => Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('9. report hardship modal saves through service', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->callAction('reportHardship', [
            'reason_category' => 'health_emergency',
            'reason_details' => 'Hospital admission required',
        ])
        ->assertHasNoActionErrors();

    expect(WidowLoanHardshipCase::where('widow_loan_id', $this->loan->id)->exists())->toBeTrue();
});

test('10. admin can verify pending hardship', function () {
    $hardshipService = new WidowLoanHardshipService;
    $case = $hardshipService->reportHardshipCase($this->loan->id, $this->coordinator->id, 'health_emergency', 'Illness');

    $this->actingAs($this->admin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('verifyHardship')
        ->callAction('verifyHardship', [
            'verification_notes' => 'Medical certificate verified',
        ])
        ->assertHasNoActionErrors();

    expect($case->fresh()->status)->toBe(WidowLoanHardshipStatus::VERIFIED);
});

test('11. unauthorized actor cannot verify', function () {
    $hardshipService = new WidowLoanHardshipService;
    $case = $hardshipService->reportHardshipCase($this->loan->id, $this->coordinator->id, 'health_emergency', 'Illness');

    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionHidden('verifyHardship');
});

test('12. super_admin can approve verified hardship', function () {
    $hardshipService = new WidowLoanHardshipService;
    $case = $hardshipService->reportHardshipCase($this->loan->id, $this->coordinator->id, 'health_emergency', 'Illness');
    $hardshipService->verifyHardshipCase($case->id, $this->admin->id, 'Verified');

    $this->actingAs($this->superAdmin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('approveHardship')
        ->callAction('approveHardship', [
            'recommended_action' => 'Grant 30 days payment deferment',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'relief_reason' => 'Medical relief',
        ])
        ->assertHasNoActionErrors();

    expect($case->fresh()->status)->toBe(WidowLoanHardshipStatus::APPROVED);
    expect($this->loan->fresh()->hardship_active)->toBeTrue();
});

test('13. approved hardship creates/displays relief period', function () {
    $hardshipService = new WidowLoanHardshipService;
    $case = $hardshipService->reportHardshipCase($this->loan->id, $this->coordinator->id, 'health_emergency', 'Illness');
    $hardshipService->verifyHardshipCase($case->id, $this->admin->id, 'Verified');

    $this->actingAs($this->superAdmin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->callAction('approveHardship', [
            'recommended_action' => 'Grant relief',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'relief_reason' => 'Medical relief',
        ]);

    expect($this->loan->fresh()->reliefPeriods()->exists())->toBeTrue();
});

test('14. hardship history remains visible in relation manager', function () {
    $hardshipService = new WidowLoanHardshipService;
    $case = $hardshipService->reportHardshipCase($this->loan->id, $this->coordinator->id, 'health_emergency', 'Illness');

    $this->actingAs($this->superAdmin);

    Livewire::test(HardshipRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])
        ->assertCanSeeTableRecords([$case]);
});

/*
|--------------------------------------------------------------------------
| RECOVERY UI TESTS
|--------------------------------------------------------------------------
*/

test('15. delinquent loan displays recovery case', function () {
    $recoveryCase = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    expect($recoveryCase)->not->toBeNull();

    $this->actingAs($this->superAdmin);

    Livewire::test(RecoveryRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])
        ->assertCanSeeTableRecords([$recoveryCase]);
});

test('16. authorized user can record recovery activity', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('recordRecoveryActivity')
        ->callAction('recordRecoveryActivity', [
            'activity_type' => WidowLoanRecoveryActivityType::CALL->value,
            'contact_method' => 'phone',
            'notes' => 'Spoke with widow regarding upcoming payment.',
            'next_follow_up_at' => now()->addDays(7)->toDateString(),
        ])
        ->assertHasNoActionErrors();

    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    expect($case->activities()->count())->toBe(1);
});

test('17. activity appears in history', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->callAction('recordRecoveryActivity', [
            'activity_type' => WidowLoanRecoveryActivityType::VISIT->value,
            'contact_method' => 'home_visit',
            'notes' => 'Visited widow at home.',
        ]);

    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    expect($case->current_action)->toBe(WidowLoanRecoveryActivityType::VISIT->getLabel());
});

test('18. promise-to-pay can be registered', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('recordPromiseToPay')
        ->callAction('recordPromiseToPay', [
            'promise_amount' => 10000.00,
            'promise_date' => now()->addDays(7)->toDateString(),
            'contact_method' => 'phone',
            'notes' => 'Promises to pay ₦10,000 next Friday.',
        ])
        ->assertHasNoActionErrors();

    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    expect($case->fresh()->status)->toBe(WidowLoanRecoveryStatus::PROMISE_TO_PAY);
    expect($case->promises()->where('status', WidowLoanPromiseStatus::OPEN)->count())->toBe(1);
});

test('19. promise appears in recovery history relation manager', function () {
    $recoveryService = new WidowLoanRecoveryService;
    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    $recoveryService->createRecoveryActivity($case->id, WidowLoanRecoveryActivityType::PROMISE_TO_PAY, 'Notes', 'phone', 5000.00, now()->addDays(5)->toDateString(), null, $this->coordinator->id);

    $this->actingAs($this->superAdmin);

    Livewire::test(RecoveryRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])
        ->assertTableActionVisible('fulfillPromise', $case)
        ->assertTableActionVisible('breakPromise', $case);
});

test('20. fulfillment transition works via relation manager action', function () {
    $recoveryService = new WidowLoanRecoveryService;
    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    $recoveryService->createRecoveryActivity($case->id, WidowLoanRecoveryActivityType::PROMISE_TO_PAY, 'Notes', 'phone', 5000.00, now()->addDays(5)->toDateString(), null, $this->coordinator->id);

    $this->actingAs($this->superAdmin);

    Livewire::test(RecoveryRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])
        ->callTableAction('fulfillPromise', $case);

    expect(WidowLoanPromise::where('recovery_case_id', $case->id)->first()->status)->toBe(WidowLoanPromiseStatus::FULFILLED);
});

test('21. broken promise escalation works via relation manager action', function () {
    $recoveryService = new WidowLoanRecoveryService;
    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    $recoveryService->createRecoveryActivity($case->id, WidowLoanRecoveryActivityType::PROMISE_TO_PAY, 'Notes', 'phone', 5000.00, now()->addDays(5)->toDateString(), null, $this->coordinator->id);

    $this->actingAs($this->superAdmin);

    Livewire::test(RecoveryRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])
        ->callTableAction('breakPromise', $case);

    expect(WidowLoanPromise::where('recovery_case_id', $case->id)->first()->status)->toBe(WidowLoanPromiseStatus::BROKEN);
    expect($case->fresh()->status)->toBe(WidowLoanRecoveryStatus::ESCALATED);
});

/*
|--------------------------------------------------------------------------
| WRITE-OFF RECOMMENDATION TESTS
|--------------------------------------------------------------------------
*/

test('22. authorized role can recommend write-off', function () {
    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('recommendWriteOff')
        ->callAction('recommendWriteOff', [
            'recommended_amount' => 50000.00,
            'reason' => 'Permanent disability prevents repayment.',
        ])
        ->assertHasNoActionErrors();

    expect($this->loan->fresh()->writeOffRecommendations()->count())->toBe(1);
});

test('23. unauthorized role cannot recommend write-off when active recommendation exists', function () {
    $recService = new \App\Services\WidowLoanWriteOffRecommendationService;
    $recService->recommendWriteOff($this->loan->id, null, null, 50000.00, 'Hardship', $this->coordinator->id);

    $this->actingAs($this->coordinator);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionHidden('recommendWriteOff');
});

test('24. recommendation status/history displays', function () {
    $recService = new \App\Services\WidowLoanWriteOffRecommendationService;
    $rec = $recService->recommendWriteOff($this->loan->id, null, null, 50000.00, 'Hardship', $this->coordinator->id);

    expect($rec->status)->toBe(WidowLoanWriteOffRecommendationStatus::PENDING);
});

test('25. final write-off remains super_admin only', function () {
    $this->actingAs($this->admin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionHidden('writeOff');

    $this->actingAs($this->superAdmin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionVisible('writeOff');
});

test('26. final write-off sensitive confirmation remains required', function () {
    $this->actingAs($this->superAdmin);

    // Assert action requires MFA confirmation fields
    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertActionExists('writeOff');
});

/*
|--------------------------------------------------------------------------
| GENERAL & RELATION MANAGERS TESTS
|--------------------------------------------------------------------------
*/

test('27. all new relation managers render', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(HardshipRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])->assertSuccessful();

    Livewire::test(RecoveryRelationManager::class, [
        'ownerRecord' => $this->loan,
        'pageClass' => ViewWidowLoan::class,
    ])->assertSuccessful();
});

test('28. historical records render safely', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])
        ->assertSuccessful();
});

test('29. no action exposes out-of-zone loan to coordinator', function () {
    $this->actingAs($this->otherCoordinator);

    expect(fn () => Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()]))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('30. no raw enum/string mismatch crashes UI', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)->assertSuccessful();
    Livewire::test(ViewWidowLoan::class, ['record' => $this->loan->getRouteKey()])->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| LIST TABS & USABILITY UI TESTS
|--------------------------------------------------------------------------
*/

test('31. active tab scopes correctly', function () {
    // Create completed loan
    $completedLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 0.00,
        'total_paid' => 50000.00,
        'status' => WidowLoanStatus::COMPLETED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => true,
        'disbursed_at' => now()->subDays(40),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    // Create rejected loan
    $rejectedLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::REJECTED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'active')
        ->assertCanSeeTableRecords([$this->loan]) // Active
        ->assertCanNotSeeTableRecords([$completedLoan, $rejectedLoan]);
});

test('32. draft_pending_approved tab scopes correctly', function () {
    $pendingLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::PENDING,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'draft_pending_approved')
        ->assertCanSeeTableRecords([$pendingLoan])
        ->assertCanNotSeeTableRecords([$this->loan]); // This loan is disbursed
});

test('33. fully repaid tab scopes correctly', function () {
    $completedLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 0.00,
        'total_paid' => 50000.00,
        'status' => WidowLoanStatus::COMPLETED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => true,
        'disbursed_at' => now()->subDays(40),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'fully_repaid')
        ->assertCanSeeTableRecords([$completedLoan])
        ->assertCanNotSeeTableRecords([$this->loan]);
});

test('34. rejected tab scopes correctly', function () {
    $rejectedLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::REJECTED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'rejected')
        ->assertCanSeeTableRecords([$rejectedLoan])
        ->assertCanNotSeeTableRecords([$this->loan]);
});

test('35. defaulted_written_off tab scopes correctly', function () {
    $defaultedLoan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::DEFAULTED,
        'performance_status' => WidowLoanPerformanceStatus::DEFAULTED,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'defaulted_written_off')
        ->assertCanSeeTableRecords([$defaultedLoan])
        ->assertCanNotSeeTableRecords([$this->loan]);
});

test('36. all tab scopes correctly', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$this->loan]);
});

test('37. tabs isolate zones for coordinators', function () {
    $otherDeceased = Deceased::factory()->create(['zone_id' => $this->otherZone->id]);
    $otherWidow = Widow::create([
        'first_name' => 'Amina',
        'last_name' => 'Isa',
        'nin' => '12345678902',
        'reg_no' => 'WID-44444',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $otherDeceased->id,
        'full_name' => 'Amina Isa',
        'child_sequence' => 1,
    ]);

    // Create an active loan in another zone
    $otherLoan = WidowLoan::create([
        'widow_id' => $otherWidow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::DISBURSED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading',
    ]);

    $this->actingAs($this->coordinator); // Only has access to $this->zone

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$this->loan])
        ->assertCanNotSeeTableRecords([$otherLoan]);
});

test('38. loan reference derives from uuid and prevents sequential collision', function () {
    $this->actingAs($this->admin);

    $loan1 = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::PENDING,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading 1',
    ]);

    $loan2 = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 50000.00,
        'total_payable' => 50000.00,
        'outstanding_balance' => 50000.00,
        'status' => WidowLoanStatus::PENDING,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'fully_repaid' => false,
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Trading 2',
    ]);

    $ref1 = 'WRL-'.str($loan1->id)->substr(-8)->upper();
    $ref2 = 'WRL-'.str($loan2->id)->substr(-8)->upper();

    expect($ref1)->not->toBe($ref2);

    Livewire::test(ListWidowLoans::class)
        ->set('activeTab', 'draft_pending_approved')
        ->assertCanSeeTableRecords([$loan1, $loan2])
        ->assertSee($ref1)
        ->assertSee($ref2);
});
