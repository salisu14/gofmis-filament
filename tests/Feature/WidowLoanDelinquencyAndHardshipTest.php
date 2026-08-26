<?php

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanHardshipStatus;
use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanPromiseStatus;
use App\Enums\WidowLoanRecoveryActivityType;
use App\Enums\WidowLoanRecoveryStatus;
use App\Enums\WidowLoanScheduleStatus;
use App\Enums\WidowLoanStatus;
use App\Enums\WidowLoanWriteOffRecommendationStatus;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanPromise;
use App\Models\WidowLoanRecoveryCase;
use App\Models\WidowLoanSchedule;
use App\Models\Zone;
use App\Services\WidowLoanDelinquencyService;
use App\Services\WidowLoanHardshipService;
use App\Services\WidowLoanRecoveryService;
use App\Services\WidowLoanRestructureService;
use App\Services\WidowLoanWriteOffRecommendationService;
use App\Services\WidowLoanWriteOffService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
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
        'first_name' => 'Sarah',
        'last_name' => 'Connor',
        'nin' => '12345678901',
        'reg_no' => 'WID-22222',
        'is_eligible' => true,
        'is_married' => false,
        'deceased_id' => $this->deceased->id,
        'full_name' => 'Sarah Connor',
        'child_sequence' => 1,
    ]);

    $this->parentBankAccount = BankAccount::create([
        'account_name' => 'Main Account',
        'account_number' => '1111111111',
        'opening_balance' => 1000000.00,
        'ledger_balance' => 1000000.00,
        'user_id' => $this->superAdmin->id,
    ]);

    $this->bankAccount = BankAccount::create([
        'account_name' => 'Disbursement Fund',
        'account_number' => '9876543210',
        'opening_balance' => 1000000.00,
        'ledger_balance' => 1000000.00,
        'parent_bank_account_id' => $this->parentBankAccount->id,
        'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
        'user_id' => $this->superAdmin->id,
    ]);

    $this->loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'principal_amount' => 60000.00,
        'total_payable' => 60000.00,
        'outstanding_balance' => 60000.00,
        'total_paid' => 0.00,
        'status' => WidowLoanStatus::DISBURSED,
        'performance_status' => WidowLoanPerformanceStatus::CURRENT,
        'disbursed_at' => now()->subDays(60),
        'collected_at' => now()->subDays(60),
        'bank_account_id' => $this->bankAccount->id,
        'purpose' => 'Farming',
    ]);

    // Create 6 schedules (₦10,000 each)
    $this->schedules = [];
    for ($i = 1; $i <= 6; $i++) {
        $this->schedules[] = WidowLoanSchedule::create([
            'widow_loan_id' => $this->loan->id,
            'installment_number' => $i,
            'amount_due' => 10000.00,
            'due_date' => now()->subDays(60)->addDays($i * 10), // Installment due every 10 days
            'is_paid' => false,
            'status' => WidowLoanScheduleStatus::PENDING,
            'schedule_version' => 1,
        ]);
    }

    // Sync schedules inside database to reflect correct state
    $this->loan->refreshBalance();
});

/*
|--------------------------------------------------------------------------
| Delinquency & Aging Tests
|--------------------------------------------------------------------------
*/

test('current loan remains current when no installments are past due', function () {
    // Move all due dates to the future
    foreach ($this->loan->schedules as $s) {
        $s->update(['due_date' => now()->addDays(10)]);
    }

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::CURRENT);
    expect($this->loan->fresh()->days_past_due)->toBe(0);
});

test('one late installment makes loan overdue', function () {
    // Oldest unpaid schedule is 20 days past due
    $this->loan->schedules()->first()->update(['due_date' => now()->subDays(20)]);
    // Make others in the future
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::OVERDUE);
    expect($this->loan->fresh()->days_past_due)->toBe(20);
});

test('loan crosses configured delinquency threshold', function () {
    // Oldest unpaid schedule is 40 days past due (threshold is 30)
    $this->loan->schedules()->first()->update(['due_date' => now()->subDays(40)]);
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::DELINQUENT);
    expect($this->loan->fresh()->days_past_due)->toBe(40);
});

test('loan crosses default threshold', function () {
    // Oldest unpaid schedule is 95 days past due (threshold is 90)
    $this->loan->schedules()->first()->update(['due_date' => now()->subDays(95)]);
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::DEFAULTED);
    expect($this->loan->fresh()->defaulted_at)->not->toBeNull();
});

test('DPD uses oldest unpaid non-waived installment', function () {
    // First installment is waived, second is overdue
    $this->loan->schedules()->where('installment_number', 1)->first()->update([
        'status' => WidowLoanScheduleStatus::WAIVED,
        'due_date' => now()->subDays(50),
    ]);
    $this->loan->schedules()->where('installment_number', 2)->first()->update([
        'due_date' => now()->subDays(20),
    ]);
    $this->loan->schedules()->where('installment_number', '>', 2)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->days_past_due)->toBe(20); // Skips waived first installment
});

test('paid schedules are ignored when calculating DPD', function () {
    // First installment is paid (₦10,000 repayment is registered)
    $repaymentService = new \App\Services\WidowLoanService;

    // Setup repayment bank usage
    $this->bankAccount->update(['usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT]);

    $repaymentService->recordRepayment(new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $this->loan->id,
        amount: 10000.00,
        paidAt: now()->subDays(30),
        paymentMethod: 'bank_transfer',
        bankAccountId: $this->bankAccount->id,
        notes: 'First installment payment'
    ));

    // First due was 50 days ago, second was 20 days ago
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(50)]);
    $this->loan->schedules()->where('installment_number', 2)->first()->update(['due_date' => now()->subDays(20)]);
    $this->loan->schedules()->where('installment_number', '>', 2)->update(['due_date' => now()->addDays(10)]);

    $this->loan = $this->loan->fresh();
    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->days_past_due)->toBe(20); // Skips first since it was fully covered by the payment
});

test('waived schedules are ignored', function () {
    $this->loan->schedules()->where('installment_number', 1)->first()->update([
        'status' => WidowLoanScheduleStatus::WAIVED,
        'due_date' => now()->subDays(60),
    ]);
    // Set other schedules to the future
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    // Oldest non-waived is in the future
    expect($this->loan->fresh()->days_past_due)->toBe(0);
});

test('overdue amount is calculated correctly', function () {
    // First three installments are past due (each is ₦10,000)
    $this->loan->schedules()->where('installment_number', '<=', 3)->update(['due_date' => now()->subDays(10)]);
    $this->loan->schedules()->where('installment_number', '>', 3)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    expect($service->calculateOverdueAmount($this->loan))->toBe(30000.00);
});

test('delinquency cures after repayment', function () {
    // 1 installment past due
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(15)]);
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);
    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::OVERDUE);

    // Pay off the overdue amount
    $repaymentService = new \App\Services\WidowLoanService;
    $this->bankAccount->update(['usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT]);
    $repaymentService->recordRepayment(new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $this->loan->id,
        amount: 10000.00,
        paidAt: now(),
        paymentMethod: 'cash',
        bankAccountId: $this->bankAccount->id,
        notes: 'Pay off arrears'
    ));

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::CURRENT);
    expect($this->loan->fresh()->days_past_due)->toBe(0);
});

test('evaluation command is idempotent', function () {
    $service = new WidowLoanDelinquencyService;
    $service->evaluateLoan($this->loan);

    $firstState = $this->loan->fresh();

    $service->evaluateLoan($this->loan);
    $secondState = $this->loan->fresh();

    expect($firstState->performance_status)->toBe($secondState->performance_status);
    expect($firstState->days_past_due)->toBe($secondState->days_past_due);
});

/*
|--------------------------------------------------------------------------
| Hardship Tests
|--------------------------------------------------------------------------
*/

test('coordinator can submit a hardship case for own zone', function () {
    $service = new WidowLoanHardshipService;

    $case = $service->reportHardshipCase(
        loanId: $this->loan->id,
        reportedById: $this->coordinator->id,
        reasonCategory: 'health_emergency',
        reasonDetails: 'Severe illness'
    );

    expect($case->status)->toBe(WidowLoanHardshipStatus::PENDING);
    expect($case->widow_loan_id)->toBe($this->loan->id);
});

test('coordinator cannot submit for another zone', function () {
    $service = new WidowLoanHardshipService;

    expect(fn () => $service->reportHardshipCase(
        loanId: $this->loan->id,
        reportedById: $this->otherCoordinator->id, // Coordinator of Zone B
        reasonCategory: 'health_emergency',
        reasonDetails: 'Severe illness'
    ))->toThrow(\RuntimeException::class, 'Unauthorized: Coordinators can only report hardship cases for widows in their own zone.');
});

test('authorized user can verify hardship', function () {
    $service = new WidowLoanHardshipService;

    $case = $service->reportHardshipCase($this->loan->id, $this->coordinator->id, 'accident', 'Car accident');

    $verifiedCase = $service->verifyHardshipCase($case->id, $this->admin->id, 'Verified medical records');

    expect($verifiedCase->status)->toBe(WidowLoanHardshipStatus::VERIFIED);
});

test('unauthorized user cannot approve hardship before verification', function () {
    $service = new WidowLoanHardshipService;
    $case = $service->reportHardshipCase($this->loan->id, $this->coordinator->id, 'accident', 'Car accident');

    expect(fn () => $service->approveHardshipCase($case->id, $this->superAdmin->id, 'restructure'))
        ->toThrow(\RuntimeException::class, 'Only verified hardship cases can be approved.');
});

test('supporting evidence document path is preserved', function () {
    $service = new WidowLoanHardshipService;
    $case = $service->reportHardshipCase(
        $this->loan->id,
        $this->coordinator->id,
        'accident',
        'Car accident',
        'evidence/doc.pdf'
    );

    expect($case->supporting_document_path)->toBe('evidence/doc.pdf');
});

test('approved hardship does not erase debt but marks hardship active', function () {
    $service = new WidowLoanHardshipService;
    $case = $service->reportHardshipCase($this->loan->id, $this->coordinator->id, 'accident', 'Car accident');
    $service->verifyHardshipCase($case->id, $this->admin->id, 'Verified');

    $approvedCase = $service->approveHardshipCase($case->id, $this->superAdmin->id, 'restructure');

    expect($approvedCase->status)->toBe(WidowLoanHardshipStatus::APPROVED);
    expect($this->loan->fresh()->hardship_active)->toBe(true);
    expect((float) $this->loan->fresh()->outstanding_balance)->toBe(60000.00); // balance remains unchanged
});

test('rejected hardship leaves repayment obligations unchanged', function () {
    $service = new WidowLoanHardshipService;
    $case = $service->reportHardshipCase($this->loan->id, $this->coordinator->id, 'accident', 'Car accident');

    $rejectedCase = $service->rejectHardshipCase($case->id, $this->admin->id, 'Not enough proof');

    expect($rejectedCase->status)->toBe(WidowLoanHardshipStatus::REJECTED);
    expect($this->loan->fresh()->hardship_active)->toBe(false);
});

/*
|--------------------------------------------------------------------------
| Relief Period Tests
|--------------------------------------------------------------------------
*/

test('approved relief pauses default classification', function () {
    $hardshipService = new WidowLoanHardshipService;

    // Set DPD to 95 days (would normally trigger DEFAULTED)
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(95)]);

    // Grant relief period
    $hardshipService->createReliefPeriod(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        startsAt: now()->subDays(5)->toDateTimeString(),
        endsAt: now()->addDays(30)->toDateTimeString(),
        reason: 'Temporary relief',
        approvedById: $this->superAdmin->id
    );

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::HARDSHIP);
});

test('expired relief allows normal evaluation to resume', function () {
    $hardshipService = new WidowLoanHardshipService;

    // Set DPD to 95 days
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(95)]);

    // Grant relief that expired yesterday
    $hardshipService->createReliefPeriod(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        startsAt: now()->subDays(10)->toDateTimeString(),
        endsAt: now()->subDays(1)->toDateTimeString(),
        reason: 'Expired relief',
        approvedById: $this->superAdmin->id
    );

    // Evaluate
    app(WidowLoanDelinquencyService::class)->evaluateLoan($this->loan);

    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::DEFAULTED);
});

/*
|--------------------------------------------------------------------------
| Restructuring Tests
|--------------------------------------------------------------------------
*/

test('restructuring preserves historical repayments and creates new schedule version', function () {
    $restructureService = new WidowLoanRestructureService;

    // Register one repayment first
    $repaymentService = new \App\Services\WidowLoanService;
    $this->bankAccount->update(['usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT]);
    $repaymentService->recordRepayment(new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $this->loan->id,
        amount: 10000.00,
        paidAt: now(),
        paymentMethod: 'cash',
        bankAccountId: $this->bankAccount->id,
        notes: 'Paid first'
    ));

    $loanBefore = $this->loan->fresh();
    expect((float) $loanBefore->total_paid)->toBe(10000.00);
    expect((float) $loanBefore->outstanding_balance)->toBe(50000.00);

    // Propose restructure (outstanding ₦50,000 restructured into 5 monthly installments)
    $restructure = $restructureService->proposeRestructure(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        newDurationMonths: 5,
        newFrequency: LoanRepaymentFrequency::MONTHLY,
        newInstallmentAmount: 10000.00,
        effectiveDate: now()->toDateString(),
        reason: 'Restructuring due to job loss',
        requestedBy: $this->coordinator->id
    );

    // Approve restructure
    $restructureService->approveAndApply($restructure->id, $this->superAdmin->id);

    $loanAfter = $this->loan->fresh();
    expect((float) $loanAfter->total_paid)->toBe(10000.00); // untouched
    expect((float) $loanAfter->outstanding_balance)->toBe(50000.00); // untouched

    // Old active schedule version 1 schedules must be marked superseded
    $supersededCount = WidowLoanSchedule::where('widow_loan_id', $this->loan->id)
        ->where('schedule_version', 1)
        ->whereNotNull('superseded_at')
        ->count();
    expect($supersededCount)->toBe(5); // 5 schedules that were unpaid became superseded

    // New version 2 schedules must exist
    $newSchedules = WidowLoanSchedule::where('widow_loan_id', $this->loan->id)
        ->where('schedule_version', 2)
        ->get();
    expect($newSchedules->count())->toBe(5);
    expect((float) $newSchedules->first()->amount_due)->toBe(10000.00);
});

test('restructure cannot be proposed twice concurrently', function () {
    $restructureService = new WidowLoanRestructureService;

    $restructureService->proposeRestructure(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        newDurationMonths: 6,
        newFrequency: LoanRepaymentFrequency::MONTHLY,
        newInstallmentAmount: 10000.00,
        effectiveDate: now()->toDateString(),
        reason: 'Restructure 1',
        requestedBy: $this->coordinator->id
    );

    expect(fn () => $restructureService->proposeRestructure(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        newDurationMonths: 6,
        newFrequency: LoanRepaymentFrequency::MONTHLY,
        newInstallmentAmount: 10000.00,
        effectiveDate: now()->toDateString(),
        reason: 'Restructure 2',
        requestedBy: $this->coordinator->id
    ))->toThrow(\RuntimeException::class, 'There is already a pending restructuring proposal for this loan.');
});

/*
|--------------------------------------------------------------------------
| Recovery Case & Action Tests
|--------------------------------------------------------------------------
*/

test('recovery case opens automatically when delinquent', function () {
    // Make other schedules in the future
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(40)]);

    app(WidowLoanDelinquencyService::class)->evaluateLoan($this->loan);

    $recoveryCase = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();
    expect($recoveryCase)->not->toBeNull();
    expect($recoveryCase->status)->toBe(WidowLoanRecoveryStatus::OPEN);
});

test('duplicate evaluation does not create duplicate recovery cases', function () {
    // Make other schedules in the future
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(40)]);

    $service = app(WidowLoanDelinquencyService::class);
    $service->evaluateLoan($this->loan);
    $service->evaluateLoan($this->loan);

    $count = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->count();
    expect($count)->toBe(1);
});

test('promise to pay can be recorded and fulfilled', function () {
    // Make other schedules in the future
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(40)]);

    app(WidowLoanDelinquencyService::class)->evaluateLoan($this->loan);
    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();

    $recoveryService = new WidowLoanRecoveryService;

    // Create promise to pay activity
    $recoveryService->createRecoveryActivity(
        caseId: $case->id,
        type: WidowLoanRecoveryActivityType::PROMISE_TO_PAY,
        notes: 'Promises to pay next week',
        contactMethod: 'phone',
        promiseAmount: 10000.00,
        promiseDate: now()->addDays(7)->toDateString(),
        performedBy: $this->coordinator->id
    );

    expect($case->fresh()->status)->toBe(WidowLoanRecoveryStatus::PROMISE_TO_PAY);

    $promise = WidowLoanPromise::where('recovery_case_id', $case->id)->first();
    expect($promise)->not->toBeNull();
    expect($promise->status)->toBe(WidowLoanPromiseStatus::OPEN);

    // Fulfill promise
    $recoveryService->fulfillPromise($promise->id);
    expect($promise->fresh()->status)->toBe(WidowLoanPromiseStatus::FULFILLED);
    expect($case->fresh()->status)->toBe(WidowLoanRecoveryStatus::IN_PROGRESS);
});

test('promise to pay can become broken', function () {
    // Make other schedules in the future
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(40)]);

    app(WidowLoanDelinquencyService::class)->evaluateLoan($this->loan);
    $case = WidowLoanRecoveryCase::where('widow_loan_id', $this->loan->id)->first();

    $recoveryService = new WidowLoanRecoveryService;

    $recoveryService->createRecoveryActivity(
        caseId: $case->id,
        type: WidowLoanRecoveryActivityType::PROMISE_TO_PAY,
        notes: 'Promises to pay next week',
        contactMethod: 'phone',
        promiseAmount: 10000.00,
        promiseDate: now()->addDays(7)->toDateString(),
        performedBy: $this->coordinator->id
    );

    $promise = WidowLoanPromise::where('recovery_case_id', $case->id)->first();

    // Break promise
    $recoveryService->breakPromise($promise->id);
    expect($promise->fresh()->status)->toBe(WidowLoanPromiseStatus::BROKEN);
    expect($case->fresh()->status)->toBe(WidowLoanRecoveryStatus::ESCALATED);
});

/*
|--------------------------------------------------------------------------
| Write-Off Recommendation Tests
|--------------------------------------------------------------------------
*/

test('authorized staff can recommend write-off but does not alter balances', function () {
    $service = new WidowLoanWriteOffRecommendationService;

    $recommendation = $service->recommendWriteOff(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        recoveryCaseId: null,
        amount: 60000.00,
        reason: 'Extreme medical hardship',
        recommendedBy: $this->coordinator->id
    );

    expect($recommendation->status)->toBe(WidowLoanWriteOffRecommendationStatus::PENDING);
    expect((float) $this->loan->fresh()->outstanding_balance)->toBe(60000.00); // balance remains unchanged
});

test('recommendations sync and transition to executed on actual super admin write-off', function () {
    $recService = new WidowLoanWriteOffRecommendationService;

    $recommendation = $recService->recommendWriteOff(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        recoveryCaseId: null,
        amount: 60000.00,
        reason: 'Extreme medical hardship',
        recommendedBy: $this->coordinator->id
    );

    // Endorse the recommendation
    $recService->reviewRecommendation($recommendation->id, $this->admin->id, 'endorsed', 'Looks genuine');

    // Run the actual Super Admin Write-Off
    $writeOffService = new WidowLoanWriteOffService;
    $writeOffService->writeOff($this->loan, $this->superAdmin, 'Approved recommendation');

    expect($this->loan->fresh()->status)->toBe(WidowLoanStatus::WRITTEN_OFF);
    expect($recommendation->fresh()->status)->toBe(WidowLoanWriteOffRecommendationStatus::EXECUTED);
});

test('loan status vs performance status separation', function () {
    $service = new WidowLoanDelinquencyService;

    // Make all schedules in the future so that it starts Current
    $this->loan->schedules()->update(['due_date' => now()->addDays(10)]);
    $this->loan = $this->loan->fresh();
    $service->evaluateLoan($this->loan);

    // Disbursed loan initially Current
    expect($this->loan->status)->toBe(WidowLoanStatus::DISBURSED);
    expect($this->loan->performance_status)->toBe(WidowLoanPerformanceStatus::CURRENT);

    // Make it overdue -> status remains DISBURSED, performance_status becomes OVERDUE
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(20)]);
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);
    $service->evaluateLoan($this->loan);

    expect($this->loan->fresh()->status)->toBe(WidowLoanStatus::DISBURSED);
    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::OVERDUE);

    // Make it completed
    $this->loan->update(['status' => WidowLoanStatus::COMPLETED]);
    $service->evaluateLoan($this->loan);
    expect($this->loan->fresh()->status)->toBe(WidowLoanStatus::COMPLETED);
    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::CURRENT);

    // Make it written-off
    $this->loan->update(['status' => WidowLoanStatus::WRITTEN_OFF]);
    $service->evaluateLoan($this->loan);
    expect($this->loan->fresh()->status)->toBe(WidowLoanStatus::WRITTEN_OFF);
    expect($this->loan->fresh()->performance_status)->toBe(WidowLoanPerformanceStatus::WRITTEN_OFF);
});

test('days past due calculation logic for future and waived installments', function () {
    $service = new WidowLoanDelinquencyService;

    // Set all installments to future dates (e.g. 10, 20, 30 days from now)
    foreach ($this->loan->schedules as $s) {
        $s->update(['due_date' => now()->addDays($s->installment_number * 10)]);
    }

    $this->loan = $this->loan->fresh();
    $service->evaluateLoan($this->loan);

    expect($this->loan->days_past_due)->toBe(0);
    expect($this->loan->performance_status)->toBe(WidowLoanPerformanceStatus::CURRENT);

    // Set installment 1 to 40 days ago, but waive it
    $this->loan->schedules()->where('installment_number', 1)->first()->update([
        'due_date' => now()->subDays(40),
        'status' => WidowLoanScheduleStatus::WAIVED,
    ]);

    $this->loan = $this->loan->fresh();
    $service->evaluateLoan($this->loan);

    // Oldest non-waived is in the future, so DPD should be 0
    expect($this->loan->days_past_due)->toBe(0);
});

test('days past due calculation with partial payment', function () {
    $service = new WidowLoanDelinquencyService;
    $repaymentService = new \App\Services\WidowLoanService;

    // Set schedule 1 to 40 days ago (₦10,000 due), others in future
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(40)]);
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    // Record partial payment of ₦4,000
    $this->bankAccount->update(['usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT]);
    $repaymentService->recordRepayment(new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $this->loan->id,
        amount: 4000.00,
        paidAt: now(),
        paymentMethod: 'cash',
        bankAccountId: $this->bankAccount->id,
        notes: 'Partial payment'
    ));

    $this->loan = $this->loan->fresh();
    $service->evaluateLoan($this->loan);

    // Schedule 1 is still partially unpaid (₦6,000 remaining), so oldest past due starts at schedule 1 (40 DPD)
    expect($this->loan->days_past_due)->toBe(40);

    // Record another ₦6,000 to fully pay it
    $repaymentService->recordRepayment(new \App\Data\Loan\RecordWidowLoanRepaymentData(
        widowLoanId: $this->loan->id,
        amount: 6000.00,
        paidAt: now(),
        paymentMethod: 'cash',
        bankAccountId: $this->bankAccount->id,
        notes: 'Pay off remaining'
    ));

    $this->loan = $this->loan->fresh();
    $service->evaluateLoan($this->loan);

    // Schedule 1 is now fully paid, other schedules are in the future -> DPD should cure to 0
    expect($this->loan->days_past_due)->toBe(0);
});

test('relief active periods boundary checks', function ($startsAtDays, $endsAtDays, $expectedStatus) {
    $hardshipService = new WidowLoanHardshipService;
    $delinquencyService = new WidowLoanDelinquencyService;

    // Set DPD to 95 days (would normally trigger DEFAULTED)
    $this->loan->schedules()->where('installment_number', 1)->first()->update(['due_date' => now()->subDays(95)]);
    $this->loan->schedules()->where('installment_number', '>', 1)->update(['due_date' => now()->addDays(10)]);

    // Create relief period
    $hardshipService->createReliefPeriod(
        loanId: $this->loan->id,
        hardshipCaseId: null,
        startsAt: now()->addDays($startsAtDays)->toDateString(),
        endsAt: now()->addDays($endsAtDays)->toDateString(),
        reason: 'Relief test',
        approvedById: $this->superAdmin->id
    );

    $this->loan = $this->loan->fresh();

    $delinquencyService->evaluateLoan($this->loan);

    expect($this->loan->performance_status)->toBe($expectedStatus);
})->with([
    [1, 30, WidowLoanPerformanceStatus::DEFAULTED],  // Day before relief starts (starts tomorrow)
    [0, 30, WidowLoanPerformanceStatus::HARDSHIP],   // Relief starts today
    [-5, 5, WidowLoanPerformanceStatus::HARDSHIP],   // During relief
    [-10, 0, WidowLoanPerformanceStatus::HARDSHIP],  // Relief ends today
    [-10, -1, WidowLoanPerformanceStatus::DEFAULTED], // Day after relief ends (ended yesterday)
]);
