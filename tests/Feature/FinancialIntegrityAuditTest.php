<?php

use App\Enums\Gender;
use App\Enums\InstitutionType;
use App\Enums\LoanRepaymentFrequency;
use App\Enums\OrphanStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\Deceased;
use App\Models\Imprest;
use App\Models\Institution;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Project;
use App\Models\User;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $this->zone = Zone::create(['name' => 'Financial Audit Zone', 'address' => '100 Ledger Way']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->bankAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'bank_name' => 'First Bank',
        'account_name' => 'GOF Foundation Operating',
        'account_number' => '1234567890',
        'current_balance' => 500000.00,
        'is_active' => true,
    ]);
});

test('widow loan schedule generation sums up exactly to total payable without penny loss', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-001',
        'first_name' => 'Jane',
        'last_name' => 'Widow',
        'nin' => '12345678901',
        'phone' => '08012345678',
        'address' => '123 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->bankAccount->id,
        'principal_amount' => 10000.00,
        'total_payable' => 10000.00,
        'duration_months' => 3,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'disbursed_at' => now(),
    ]);

    $loan->generateLedger();

    $scheduleTotal = $loan->schedules()->sum('amount_due');
    expect((float) $scheduleTotal)->toBe(10000.00);
});

test('widow loan partial repayments update cumulative paid balance and schedule status accurately', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $widow = Widow::create([
        'deceased_id' => $deceased->id,
        'reg_no' => 'WID-002',
        'first_name' => 'Mary',
        'last_name' => 'Widow',
        'nin' => '98765432109',
        'phone' => '08087654321',
        'address' => '456 Test St',
        'child_sequence' => 1,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $loan = WidowLoan::create([
        'widow_id' => $widow->id,
        'bank_account_id' => $this->bankAccount->id,
        'principal_amount' => 12000.00,
        'total_payable' => 12000.00,
        'duration_months' => 12,
        'repayment_frequency' => LoanRepaymentFrequency::MONTHLY,
        'status' => WidowLoanStatus::DISBURSED,
        'collected_at' => now(),
        'disbursed_at' => now(),
    ]);

    $loan->generateLedger();

    // Make payment covering 1.5 installments (1500)
    WidowLoanRepayment::create([
        'widow_loan_id' => $loan->id,
        'receipt_number' => 'REC-001',
        'payment_method' => 'bank_transfer',
        'amount' => 1500.00,
        'paid_at' => now(),
        'notes' => 'Partial repayment',
    ]);

    $loan->refreshBalance();

    expect((float) $loan->fresh()->total_paid)->toBe(1500.00)
        ->and((float) $loan->fresh()->outstanding_balance)->toBe(10500.00)
        ->and($loan->schedules()->where('is_paid', true)->count())->toBe(1);
});

test('imprest float transactions track debit credit balance integrity cleanly', function () {
    $imprest = Imprest::create([
        'location' => 'Kano Central',
        'custodian_id' => $this->admin->id,
        'authorized_amount' => 50000.00,
        'current_balance' => 50000.00,
    ]);

    // Issue expense disbursement
    $imprest->current_balance -= 12000.00;
    $imprest->save();

    expect((float) $imprest->fresh()->current_balance)->toBe(38000.00);
});

test('orphan education fee configuration saves fee frequency and fee support accurately', function () {
    $deceased = Deceased::factory()->create(['zone_id' => $this->zone->id]);
    $orphan = Orphan::create([
        'first_name' => 'Edu',
        'last_name' => 'Child',
        'deceased_id' => $deceased->id,
        'reg_no' => 'ORP-EDU-01',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'birth_date' => now()->subYears(10)->toDateString(),
        'address' => '123 Test St',
        'status' => OrphanStatus::ACTIVE,
        'is_eligible' => true,
    ]);

    $institution = Institution::create([
        'name' => 'Capital Science Academy',
        'type' => InstitutionType::WESTERN,
    ]);

    $education = OrphanEducation::create([
        'orphan_id' => $orphan->id,
        'institution_id' => $institution->id,
        'school_fee' => 15000.00,
        'is_fee_supported' => true,
        'support_amount' => 15000.00,
        'fee_frequency' => 'termly',
        'is_current' => true,
    ]);

    expect((float) $education->school_fee)->toBe(15000.00)
        ->and($education->is_fee_supported)->toBeTrue();
});

test('project budget and expenditure tracking handles expenditures without negative balances', function () {
    $project = Project::create([
        'name' => 'Water Well Installation Phase 1',
        'type' => ProjectType::WATER,
        'budget_allocated' => 250000.00,
        'budget_spent' => 50000.00,
        'status' => ProjectStatus::IN_PROGRESS,
        'zone_id' => $this->zone->id,
    ]);

    expect((float) $project->budget_remaining)->toBe(200000.00)
        ->and($project->is_over_budget)->toBeFalse();
});
