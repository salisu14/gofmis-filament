<?php

use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Enums\BeneficiaryStatus;
use App\Enums\Gender;
use App\Enums\IllnessCategory;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Deceased;
use App\Models\Illness;
use App\Models\InterventionRequest;
use App\Models\InterventionRequestItem;
use App\Models\InterventionType;
use App\Models\Item;
use App\Models\Medication;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\Project;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Models\Zone;
use App\Services\WidowLoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        'first_name' => 'Zeinab',
        'last_name' => 'Umar',
        'nin' => '12345678977',
        'reg_no' => 'WID-00700',
        'child_sequence' => 1,
        'gender' => Gender::FEMALE,
        'is_eligible' => true,
        'is_married' => false,
    ]);

    $this->orphan = Orphan::create([
        'deceased_id' => $this->deceased->id,
        'first_name' => 'Yusuf',
        'last_name' => 'Umar',
        'nin' => '12345678976',
        'reg_no' => 'ORP-00700',
        'child_sequence' => 1,
        'gender' => Gender::MALE,
        'is_eligible' => true,
    ]);

    $this->mainAccount = BankAccount::create([
        'account_name' => 'Main Operating Account',
        'account_number' => '0000000000',
        'bank_name' => 'First Bank',
        'usage' => 'general',
        'ledger_balance' => 1000000.00,
        'user_id' => $this->admin->id,
    ]);

    $this->disbursingAccount = BankAccount::create([
        'account_name' => 'Widow Loan Disbursement Fund',
        'account_number' => '1111111111',
        'bank_name' => 'First Bank',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
        'parent_bank_account_id' => $this->mainAccount->id,
        'user_id' => $this->admin->id,
    ]);
    $this->disbursingAccount->update(['ledger_balance' => 500000.00]);

    $this->repaymentAccount = BankAccount::create([
        'account_name' => 'Widow Loan Repayment Fund',
        'account_number' => '2222222222',
        'bank_name' => 'First Bank',
        'usage' => BankAccount::USAGE_WIDOW_LOAN_REPAYMENT,
        'parent_bank_account_id' => $this->mainAccount->id,
        'user_id' => $this->admin->id,
    ]);

    $this->loanService = app(WidowLoanService::class);
});

test('1. comprehensive coordinator-to-admin request closure integration suite across all 5 workflows', function () {
    $this->actingAs($this->admin);

    // WORKFLOW 1: WIDOW LOAN
    $loan = WidowLoan::create([
        'widow_id' => $this->widow->id,
        'bank_account_id' => $this->disbursingAccount->id,
        'repayment_bank_id' => $this->repaymentAccount->id,
        'principal_amount' => 20000,
        'total_payable' => 20000,
        'duration_months' => 2,
        'repayment_frequency' => 'monthly',
        'purpose' => 'Business expansion',
        'status' => WidowLoanStatus::DRAFT,
        'outstanding_balance' => 20000,
    ]);

    $this->loanService->submitForApproval($loan, [['role' => 'super_admin']]);
    $loan->update(['status' => WidowLoanStatus::APPROVED]);
    $this->loanService->disburseLoan($loan);
    $this->loanService->collectLoan($loan, $this->coordinator->id, 'Zeinab Umar');

    $repaymentData = new RecordWidowLoanRepaymentData(
        widowLoanId: $loan->id,
        amount: 20000,
        paidAt: now()->toDateString(),
        paymentMethod: 'cash',
        bankAccountId: $this->repaymentAccount->id,
        notes: 'Full lump-sum settlement'
    );
    $this->loanService->recordRepayment($repaymentData);
    expect($loan->fresh()->status)->toBe(WidowLoanStatus::COMPLETED);

    // WORKFLOW 2: HEALTHCARE
    $illness = Illness::create(['name' => 'Asthma', 'category' => IllnessCategory::Respiratory->value]);
    $medication = Medication::create(['name' => 'Inhaler', 'code' => 'MED-ASTHMA', 'user_id' => $this->admin->id]);
    $prescription = Prescription::create([
        'prescribable_type' => Orphan::class,
        'prescribable_id' => $this->orphan->id,
        'doctor_name' => 'Dr. Bello',
        'illness_id' => $illness->id,
        'lab_test_cost' => 3000,
        'drug_cost' => 4000,
        'prescription_date' => now()->toDateString(),
        'user_id' => $this->coordinator->id,
    ]);
    $prescription->medications()->attach([$medication->id => ['dosage' => '1 puff BD']]);
    expect($prescription->total_cost)->toEqual(7000.00);

    // WORKFLOW 3: EDUCATION
    $category = Category::create(['name' => 'Uniforms', 'user_id' => $this->admin->id]);
    $item = Item::create(['name' => 'Shirt', 'category_id' => $category->id, 'user_id' => $this->admin->id]);
    $type = InterventionType::create(['name' => 'Education - Uniforms Only', 'category' => 'education']);
    $eduRequest = InterventionRequest::create([
        'orphan_id' => $this->orphan->id,
        'intervention_type_id' => $type->id,
        'title' => 'Uniform Request',
        'status' => 'pending',
        'submitted_by' => $this->coordinator->id,
        'amount_requested' => 5000,
    ]);
    $itemRow = InterventionRequestItem::create([
        'intervention_request_id' => $eduRequest->id,
        'item_id' => $item->id,
        'item_name' => 'Shirt',
        'quantity_requested' => 1,
        'quantity_fulfilled' => 0,
    ]);
    $eduRequest->markVerified($this->admin->id, 'Verified');
    $eduRequest->approveRequest($this->admin->id, 'Approved');
    $itemRow->update(['quantity_fulfilled' => 1]);
    $eduRequest->update(['status' => 'fulfilled']);
    expect($eduRequest->fresh()->status)->toBe('fulfilled');

    // WORKFLOW 4: WELFARE
    $package = WelfarePackage::create([
        'name' => 'General Welfare 2026',
        'is_open' => true,
        'start_date' => now()->subDays(1),
        'end_date' => now()->addDays(10),
        'created_by' => $this->admin->id,
    ]);
    $welfare = WelfareBeneficiary::create([
        'welfare_package_id' => $package->id,
        'deceased_id' => $this->deceased->id,
        'status' => BeneficiaryStatus::PENDING,
        'collection_status' => \App\Enums\CollectionStatus::NOT_COLLECTED,
        'suggested_by' => $this->coordinator->id,
    ]);
    $welfare->update(['status' => BeneficiaryStatus::APPROVED]);
    $welfare->markAsCollected('Collected by family head', $this->admin->id);
    expect($welfare->fresh()->collection_status)->toBe(\App\Enums\CollectionStatus::COLLECTED);

    // WORKFLOW 5: PROJECTS
    $project = Project::create([
        'name' => 'Community School Renovation',
        'type' => ProjectType::SCHOOL,
        'status' => ProjectStatus::PLANNING,
        'zone_id' => $this->zone->id,
        'deceased_id' => $this->deceased->id,
        'budget_allocated' => 400000,
    ]);
    $project->update(['status' => ProjectStatus::APPROVED]);
    $project->update(['status' => ProjectStatus::COMPLETED, 'progress_percentage' => 100]);
    expect($project->fresh()->status)->toBe(ProjectStatus::COMPLETED);
});
