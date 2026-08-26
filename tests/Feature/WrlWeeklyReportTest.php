<?php

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\WidowLoanSchedule;
use App\Models\Zone;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');

    $this->coordinatorA = User::factory()->create(['is_active' => true]);
    $this->coordinatorA->assignRole('coordinator');

    $this->coordinatorB = User::factory()->create(['is_active' => true]);
    $this->coordinatorB->assignRole('coordinator');

    $this->zoneA = Zone::create(['name' => 'Zone A', 'coordinator_id' => $this->coordinatorA->id]);
    $this->zoneB = Zone::create(['name' => 'Zone B', 'coordinator_id' => $this->coordinatorB->id]);

    $this->coordinatorA->unsetRelation('coordinatedZone');

    $this->deceasedA = Deceased::factory()->create(['zone_id' => $this->zoneA->id, 'full_name' => 'Deceased A']);
    $this->deceasedB = Deceased::factory()->create(['zone_id' => $this->zoneB->id, 'full_name' => 'Deceased B']);

    $bank = BankAccount::create([
        'account_name' => 'WRL Bank',
        'account_number' => '1010101010',
        'opening_balance' => 1000000.00,
        'ledger_balance' => 1000000.00,
        'user_id' => $this->admin->id,
    ]);

    // In-week anchor (a mid-week day) for both scheduled due_date and paid_at.
    $this->inWeek = Carbon::now()->startOfWeek()->addDays(2);
    $this->outside = Carbon::now()->startOfWeek()->subDay(); // previous Sunday

    $this->loanA = makeLoan($this->zoneA, $bank, 'Fatima', 'A', 'WID-A-001');
    $this->loanB = makeLoan($this->zoneA, $bank, 'Mariam', 'B', 'WID-A-002');
    $this->loanC = makeLoan($this->zoneB, $bank, 'Halima', 'C', 'WID-B-001');

    // A: scheduled 2000 / paid 2000 (Zone A)
    $this->scheduleA = WidowLoanSchedule::create([
        'widow_loan_id' => $this->loanA->id,
        'installment_number' => 1,
        'amount_due' => 2000.00,
        'due_date' => $this->inWeek,
        'is_paid' => true,
        'status' => \App\Enums\WidowLoanScheduleStatus::PAID,
    ]);
    WidowLoanRepayment::create([
        'widow_loan_id' => $this->loanA->id,
        'amount' => 2000.00,
        'paid_at' => $this->inWeek,
        'payment_method' => 'cash',
        'receipt_number' => 1001,
    ]);

    // C: scheduled 3000 / paid 1000 (Zone A)
    $this->scheduleB = WidowLoanSchedule::create([
        'widow_loan_id' => $this->loanB->id,
        'installment_number' => 1,
        'amount_due' => 3000.00,
        'due_date' => $this->inWeek,
        'is_paid' => false,
        'status' => \App\Enums\WidowLoanScheduleStatus::PENDING,
    ]);
    WidowLoanRepayment::create([
        'widow_loan_id' => $this->loanB->id,
        'amount' => 1000.00,
        'paid_at' => $this->inWeek,
        'payment_method' => 'cash',
        'receipt_number' => 1002,
    ]);

    // B: scheduled 2000 / paid 0 (Zone B)
    $this->scheduleC = WidowLoanSchedule::create([
        'widow_loan_id' => $this->loanC->id,
        'installment_number' => 1,
        'amount_due' => 2000.00,
        'due_date' => $this->inWeek,
        'is_paid' => false,
        'status' => \App\Enums\WidowLoanScheduleStatus::OVERDUE,
    ]);
});

function makeLoan(Zone $zone, BankAccount $bank, string $first, string $last, string $regNo): WidowLoan
{
    $deceased = \App\Models\Deceased::factory()->create(['zone_id' => $zone->id, 'full_name' => "Deceased {$first}"]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'first_name' => $first,
        'last_name' => $last,
        'full_name' => "{$first} {$last}",
        'reg_no' => $regNo,
        'nin' => fake()->unique()->numerify('###########'),
        'is_eligible' => true,
        'is_married' => false,
        'child_sequence' => 1,
    ]);

    return WidowLoan::create([
        'widow_id' => $widow->id,
        'status' => WidowLoanStatus::DISBURSED,
        'principal_amount' => 100000.00,
        'total_payable' => 100000.00,
        'outstanding_balance' => ($first === 'Fatima') ? 0.00 : 95000.00,
        'duration_months' => 5,
        'repayment_frequency' => LoanRepaymentFrequency::WEEKLY,
        'bank_account_id' => $bank->id,
        'disbursement_bank_id' => $bank->id,
        'repayment_bank_id' => $bank->id,
    ]);
}

function weeklyBuild(array $ctx, ?string $zone = null, bool $admin = true): array
{
    return app(\App\Services\WidowLoanWeeklyReportService::class)->build(
        weekAnchor: $ctx['inWeek']->toDateString(),
        zoneId: $zone,
        user: $admin ? $ctx['admin'] : $ctx['coordinatorA'],
        canFilterZone: $admin,
    );
}

// A. scheduled 2000 / paid 2000
test('weekly expected/collected/shortfall reconcile a fully paid instalment', function () {
    $report = weeklyBuild(get_object_vars($this));

    $rowA = $report['rows']->firstWhere('loan.id', $this->loanA->id);

    expect((float) $rowA['expected'])->toBe(2000.0)
        ->and((float) $rowA['actual'])->toBe(2000.0)
        ->and((float) $rowA['shortfall'])->toBe(0.0);
});

// B. scheduled 2000 / paid 0 -> appears with NO COLLECTION and full shortfall
test('weekly report includes a due instalment with zero collection', function () {
    $report = weeklyBuild(get_object_vars($this));

    $rowC = $report['rows']->firstWhere('loan.id', $this->loanC->id);

    expect($rowC)->not->toBeNull()
        ->and((float) $rowC['expected'])->toBe(2000.0)
        ->and((float) $rowC['actual'])->toBe(0.0)
        ->and((float) $rowC['shortfall'])->toBe(2000.0)
        ->and($rowC['collected'])->toBeFalse()
        ->and($report['distinct_loans'])->toBe(3);
});

// C. scheduled 3000 / paid 1000
test('weekly expected/collected/shortfall reconcile a partially paid instalment', function () {
    $report = weeklyBuild(get_object_vars($this));

    $rowB = $report['rows']->firstWhere('loan.id', $this->loanB->id);

    expect((float) $rowB['expected'])->toBe(3000.0)
        ->and((float) $rowB['actual'])->toBe(1000.0)
        ->and((float) $rowB['shortfall'])->toBe(2000.0);
});

// D. aggregate
test('weekly aggregate totals reconcile across all due instalments', function () {
    $report = weeklyBuild(get_object_vars($this));

    expect((float) $report['expected_total'])->toBe(7000.0)
        ->and((float) $report['collected_total'])->toBe(3000.0)
        ->and((float) $report['shortfall_total'])->toBe(4000.0)
        ->and($report['schedule_count'])->toBe(3);
});

// E. outside-week schedules and repayments excluded
test('weekly report excludes outside-week schedules and repayments', function () {
    // Due OUTSIDE the week, and a repayment OUTSIDE the week, for loan A.
    WidowLoanSchedule::create([
        'widow_loan_id' => $this->loanA->id,
        'installment_number' => 2,
        'amount_due' => 9999.00,
        'due_date' => $this->outside,
        'is_paid' => false,
        'status' => \App\Enums\WidowLoanScheduleStatus::PENDING,
    ]);
    WidowLoanRepayment::create([
        'widow_loan_id' => $this->loanA->id,
        'amount' => 9999.00,
        'paid_at' => $this->outside,
        'payment_method' => 'cash',
        'receipt_number' => 9001,
    ]);

    $report = weeklyBuild(get_object_vars($this));

    $rowA = $report['rows']->firstWhere('loan.id', $this->loanA->id);

    expect((float) $report['expected_total'])->toBe(7000.0) // 9999 excluded from expected
        ->and((float) $report['collected_total'])->toBe(3000.0) // 9999 excluded from collected
        ->and((float) $rowA['expected'])->toBe(2000.0);
});

// F. coordinator sees only own-zone scheduled obligations and collections
test('coordinator weekly report is zone-scoped for schedules and collections', function () {
    $reportForA = app(\App\Services\WidowLoanWeeklyReportService::class)->build(
        weekAnchor: $this->inWeek->toDateString(),
        zoneId: null,
        user: $this->coordinatorA,
        canFilterZone: false,
    );

    expect($reportForA['distinct_loans'])->toBe(2)
        ->and((float) $reportForA['expected_total'])->toBe(5000.0)
        ->and((float) $reportForA['collected_total'])->toBe(3000.0)
        ->and((float) $reportForA['shortfall_total'])->toBe(2000.0)
        ->and($reportForA['rows']->pluck('loan.id'))->not->toContain($this->loanC->id);

    $reportForB = app(\App\Services\WidowLoanWeeklyReportService::class)->build(
        weekAnchor: $this->inWeek->toDateString(),
        zoneId: null,
        user: $this->coordinatorB,
        canFilterZone: false,
    );

    expect($reportForB['distinct_loans'])->toBe(1)
        ->and($reportForB['rows']->pluck('loan.id'))->toContain($this->loanC->id)
        ->and((float) $reportForB['shortfall_total'])->toBe(2000.0);
});

// G. empty week renders cleanly
test('empty week renders cleanly', function () {
    $lastYear = Carbon::now()->subYear()->toDateString();

    $response = $this->actingAs($this->admin)
        ->get(route('wrl.weekly.download', ['week' => $lastYear]));

    $response->assertOk();
});

test('admin can generate weekly report for the selected week', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('wrl.weekly.download', ['week' => $this->inWeek->toDateString()]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('weekly report view shows week period and zero-collection rows', function () {
    $report = weeklyBuild(get_object_vars($this));

    $view = view('pdf.reports.wrl-weekly-repayment-report-thermal', [
        'rows' => $report['rows'],
        'weekStart' => $report['week_start'],
        'weekEnd' => $report['week_end'],
        'zone' => $report['zone_name'],
        'scheduleCount' => $report['schedule_count'],
        'repaymentCount' => 2,
        'distinctLoans' => $report['distinct_loans'],
        'expectedTotal' => $report['expected_total'],
        'collectedTotal' => $report['collected_total'],
        'shortfallTotal' => $report['shortfall_total'],
        'remainingBalanceTotal' => $report['remaining_balance_total'],
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
        'generatedAt' => $report['generated_at'],
    ])->render();

    expect($view)
        ->toContain('WRL WEEKLY REPAYMENT REPORT')
        ->toContain($report['week_start']->format('d/m/Y'))
        ->toContain($report['week_end']->format('d/m/Y'))
        ->toContain('7,000')   // expected total
        ->toContain('3,000')   // collected total
        ->toContain('4,000')   // shortfall total
        ->toContain('Halima C')
        ->toContain('NO COLLECTION');
});

test('coordinator cannot request another zone via query param', function () {
    $response = $this->actingAs($this->coordinatorA)
        ->get(route('wrl.weekly.download', ['week' => $this->inWeek->toDateString(), 'zone' => $this->zoneB->id]));

    expect($response->status())->toBe(403);
});

test('admin may filter weekly report by zone', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('wrl.weekly.download', ['week' => $this->inWeek->toDateString(), 'zone' => $this->zoneA->id]));

    $response->assertOk();
});

test('weekly report uses 58mm paper configuration', function () {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.wrl-weekly-repayment-report-thermal', [
        'rows' => collect(),
        'weekStart' => Carbon::now()->startOfWeek(),
        'weekEnd' => Carbon::now()->endOfWeek(),
        'zone' => null,
        'scheduleCount' => 0,
        'repaymentCount' => 0,
        'distinctLoans' => 0,
        'expectedTotal' => 0.0,
        'collectedTotal' => 0.0,
        'shortfallTotal' => 0.0,
        'remainingBalanceTotal' => 0.0,
        'company' => app(\App\Services\Company\CompanyInformationService::class)->reportHeader(),
        'generatedAt' => now(),
    ]);
    $pdf->setPaper([0, 0, 164.41, 1500], 'portrait');

    $output = $pdf->output();

    expect(strlen($output))->toBeGreaterThan(1000);
});

test('individual thermal receipt remains separate and working', function () {
    $repayment = WidowLoanRepayment::create([
        'widow_loan_id' => $this->loanA->id,
        'amount' => 2000.00,
        'paid_at' => $this->inWeek,
        'payment_method' => 'cash',
        'receipt_number' => 3001,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('repayments.thermal-receipt.download', ['repayment' => $repayment]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});
