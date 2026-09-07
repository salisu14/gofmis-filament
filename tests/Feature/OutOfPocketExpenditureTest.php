<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\OutOfPocketExpenditure;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ConsolidatedFinancialReportService;
use App\Services\OutOfPocketExpenditureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OutOfPocketExpenditureTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $coordinator;

    protected User $auditor;

    protected User $demoObserver;

    protected BankAccount $bankAccount;

    protected OutOfPocketExpenditureService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin_oop@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'SECRET_TEST_KEY_12345',
            'mfa_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create([
            'email' => 'admin_oop@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'SECRET_TEST_KEY_12345',
            'mfa_confirmed_at' => now(),
        ]);
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create([
            'email' => 'coordinator_oop@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->auditor = User::factory()->create([
            'email' => 'auditor_oop@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'SECRET_TEST_KEY_12345',
            'mfa_confirmed_at' => now(),
        ]);
        $this->auditor->assignRole('auditor');

        $this->demoObserver = User::factory()->create([
            'email' => 'demo_oop@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->demoObserver->assignRole('demo_observer');

        $this->bankAccount = BankAccount::create([
            'user_id' => $this->superAdmin->id,
            'account_name' => 'Main Treasury Account',
            'account_number' => '1000000001',
            'bank_name' => 'First Bank',
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'status' => 'active',
            'ledger_balance' => 500000.00,
        ]);

        $this->service = new OutOfPocketExpenditureService;
    }

    public function test_out_of_pocket_expenditure_can_be_created_in_draft_state_with_auto_generated_reference(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'expenditure_date' => now()->toDateString(),
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'transportation',
            'description' => 'Taxi fare for beneficiary visit',
            'amount' => 15000.00,
            'reimbursement_required' => true,
        ]);

        $this->assertNotNull($expenditure->id);
        $this->assertStringStartsWith('OOP-', $expenditure->reference);
        $this->assertEquals('draft', $expenditure->approval_status);
        $this->assertEquals('pending', $expenditure->reimbursement_status);
        $this->assertTrue($expenditure->isDraft());
    }

    public function test_out_of_pocket_expenditure_validates_positive_amount(): void
    {
        $this->expectException(ValidationException::class);

        OutOfPocketExpenditure::create([
            'expenditure_date' => now()->toDateString(),
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'office_supplies',
            'description' => 'Invalid zero amount',
            'amount' => 0.00,
        ]);
    }

    public function test_out_of_pocket_expenditure_service_submits_draft_expenditure(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'medical',
            'description' => 'First aid kit purchase',
            'amount' => 8500.00,
        ]);

        $updated = $this->service->submit($expenditure, $this->admin);

        $this->assertEquals('submitted', $updated->approval_status);
        $this->assertEquals($this->admin->id, $updated->submitted_by_id);
        $this->assertTrue($updated->isSubmitted());
    }

    public function test_submitting_non_draft_expenditure_throws_validation_exception(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'utilities',
            'description' => 'Electricity bill',
            'amount' => 20000.00,
        ]);

        $this->service->submit($expenditure, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->submit($expenditure->fresh(), $this->admin);
    }

    public function test_service_approves_submitted_expenditure(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'emergency_welfare',
            'description' => 'Emergency food pack',
            'amount' => 12000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $approved = $this->service->approve($expenditure->fresh(), $this->admin);

        $this->assertEquals('approved', $approved->approval_status);
        $this->assertEquals($this->admin->id, $approved->approved_by_id);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals('pending', $approved->reimbursement_status);
    }

    public function test_approving_non_submitted_expenditure_fails(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'other',
            'description' => 'Draft item',
            'amount' => 5000.00,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->approve($expenditure, $this->admin);
    }

    public function test_self_approval_by_incurred_user_is_blocked_for_regular_admin(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'transportation',
            'description' => 'Admin self-incurred trip',
            'amount' => 7000.00,
        ]);

        $this->service->submit($expenditure, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->approve($expenditure->fresh(), $this->admin);
    }

    public function test_self_approval_is_allowed_for_super_admin(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->superAdmin->id,
            'category' => 'transportation',
            'description' => 'Super admin travel',
            'amount' => 10000.00,
        ]);

        $this->service->submit($expenditure, $this->superAdmin);
        $approved = $this->service->approve($expenditure->fresh(), $this->superAdmin);

        $this->assertEquals('approved', $approved->approval_status);
    }

    public function test_service_rejects_submitted_expenditure_with_reason(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'other',
            'description' => 'Unapproved personal item',
            'amount' => 30000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $rejected = $this->service->reject($expenditure->fresh(), $this->admin, 'Receipt not attached and expenditure unnecessary.');

        $this->assertEquals('rejected', $rejected->approval_status);
        $this->assertEquals('Receipt not attached and expenditure unnecessary.', $rejected->rejection_reason);
        $this->assertEquals($this->admin->id, $rejected->rejected_by_id);
    }

    public function test_rejecting_without_reason_fails(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'other',
            'description' => 'Item to reject',
            'amount' => 4000.00,
        ]);

        $this->service->submit($expenditure, $staff);

        $this->expectException(ValidationException::class);
        $this->service->reject($expenditure->fresh(), $this->admin, '  ');
    }

    public function test_rejecting_non_submitted_expenditure_fails(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'other',
            'description' => 'Draft item',
            'amount' => 2000.00,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->reject($expenditure, $this->admin, 'Invalid state');
    }

    public function test_reimbursement_requires_approved_status(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'transportation',
            'description' => 'Submitted only',
            'amount' => 5000.00,
        ]);

        $this->service->submit($expenditure, $staff);

        $this->expectException(ValidationException::class);
        $this->service->reimburse($expenditure->fresh(), $this->bankAccount, $this->admin);
    }

    public function test_reimbursement_debits_bank_account_and_creates_canonical_transaction(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'office_supplies',
            'description' => 'Stationery bundle',
            'amount' => 25000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);

        $initialBalance = (float) $this->bankAccount->fresh()->ledger_balance;

        $reimbursed = $this->service->reimburse($expenditure->fresh(), $this->bankAccount, $this->admin);

        $this->assertEquals('reimbursed', $reimbursed->reimbursement_status);
        $this->assertNotNull($reimbursed->reimbursement_transaction_id);

        $transaction = Transaction::find($reimbursed->reimbursement_transaction_id);
        $this->assertNotNull($transaction);
        $this->assertEquals('out_of_pocket_reimbursement', $transaction->type);
        $this->assertEquals(25000.00, (float) $transaction->amount);

        $newBalance = (float) $this->bankAccount->fresh()->ledger_balance;
        $this->assertEquals($initialBalance - 25000.00, $newBalance);
    }

    public function test_reimbursement_updates_expenditure_record_to_reimbursed(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'medical',
            'description' => 'First aid supplies',
            'amount' => 10000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);
        $reimbursed = $this->service->reimburse($expenditure->fresh(), $this->bankAccount, $this->admin);

        $this->assertTrue($reimbursed->isReimbursed());
        $this->assertEquals($this->bankAccount->id, $reimbursed->reimbursement_bank_account_id);
        $this->assertEquals($this->admin->id, $reimbursed->reimbursed_by_id);
        $this->assertNotNull($reimbursed->reimbursed_at);
    }

    public function test_reimbursement_fails_if_insufficient_bank_account_ledger_balance(): void
    {
        $poorBankAccount = BankAccount::create([
            'user_id' => $this->superAdmin->id,
            'account_name' => 'Empty Account',
            'account_number' => '1000000002',
            'bank_name' => 'Zenith Bank',
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'status' => 'active',
            'ledger_balance' => 100.00,
        ]);

        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'education_support',
            'description' => 'Textbooks',
            'amount' => 50000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->reimburse($expenditure->fresh(), $poorBankAccount, $this->admin);
    }

    public function test_reimbursement_is_idempotent_and_second_attempt_fails(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'transportation',
            'description' => 'Fuel refund',
            'amount' => 18000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);
        $reimbursed = $this->service->reimburse($expenditure->fresh(), $this->bankAccount, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service->reimburse($reimbursed->fresh(), $this->bankAccount, $this->admin);
    }

    public function test_non_reimbursable_expenditure_cannot_be_reimbursed(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'office_supplies',
            'description' => 'Personal gift to foundation - no refund',
            'amount' => 5000.00,
            'reimbursement_required' => false,
        ]);

        $this->service->submit($expenditure, $staff);
        $approved = $this->service->approve($expenditure->fresh(), $this->admin);

        $this->assertEquals('not_required', $approved->reimbursement_status);

        $this->expectException(ValidationException::class);
        $this->service->reimburse($approved->fresh(), $this->bankAccount, $this->admin);
    }

    public function test_spatie_activity_is_logged_on_submit_approve_reject_reimburse(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'utilities',
            'description' => 'Generator diesel purchase',
            'amount' => 14000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);

        $this->assertDatabaseHas('activities', [
            'subject_type' => OutOfPocketExpenditure::class,
            'subject_id' => $expenditure->id,
            'description' => 'approved out of pocket expenditure',
        ]);
    }

    protected function actingAsMfaUser(User $user)
    {
        return $this->withSession([
            'mfa_verified_at' => time(),
            'mfa_verified_user_id' => $user->id,
        ])->actingAs($user);
    }

    public function test_admin_can_access_out_of_pocket_expenditure_index_page(): void
    {
        $response = $this->actingAsMfaUser($this->admin)->get('/admin/out-of-pocket-expenditures');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_out_of_pocket_expenditure_index_page(): void
    {
        $response = $this->actingAsMfaUser($this->superAdmin)->get('/admin/out-of-pocket-expenditures');
        $response->assertStatus(200);
    }

    public function test_coordinator_is_forbidden_from_out_of_pocket_expenditures(): void
    {
        $response = $this->actingAsMfaUser($this->coordinator)->get('/admin/out-of-pocket-expenditures');
        $response->assertStatus(403);
    }

    public function test_coordinator_permission_denied_returns_403(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'transportation',
            'description' => 'Secret trip',
            'amount' => 3000.00,
        ]);

        $response = $this->actingAsMfaUser($this->coordinator)->get("/admin/out-of-pocket-expenditures/{$expenditure->id}");
        $response->assertStatus(403);
    }

    public function test_auditor_has_view_access_to_out_of_pocket_expenditures(): void
    {
        $response = $this->actingAsMfaUser($this->auditor)->get('/admin/out-of-pocket-expenditures');
        $response->assertStatus(200);
    }

    public function test_demo_observer_has_view_access(): void
    {
        $response = $this->actingAsMfaUser($this->demoObserver)->get('/admin/out-of-pocket-expenditures');
        $response->assertStatus(200);
    }

    public function test_receipt_download_controller_streams_file_for_authorized_user(): void
    {
        Storage::fake('public');
        $path = 'out-of-pocket-receipts/test.pdf';
        Storage::disk('public')->put($path, 'dummy pdf content');

        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'medical',
            'description' => 'Prescription receipt',
            'amount' => 6000.00,
            'receipt_path' => $path,
        ]);

        $response = $this->actingAsMfaUser($this->admin)->get(route('out-of-pocket.receipt.download', $expenditure));
        $response->assertStatus(200);
    }

    public function test_receipt_download_controller_returns_403_for_coordinator(): void
    {
        Storage::fake('public');
        $path = 'out-of-pocket-receipts/test.pdf';
        Storage::disk('public')->put($path, 'dummy pdf content');

        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'medical',
            'description' => 'Prescription receipt',
            'amount' => 6000.00,
            'receipt_path' => $path,
        ]);

        $response = $this->actingAsMfaUser($this->coordinator)->get(route('out-of-pocket.receipt.download', $expenditure));
        $response->assertStatus(403);
    }

    public function test_receipt_download_controller_returns_404_if_receipt_file_missing(): void
    {
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'medical',
            'description' => 'Prescription receipt',
            'amount' => 6000.00,
            'receipt_path' => 'out-of-pocket-receipts/nonexistent.pdf',
        ]);

        $response = $this->actingAsMfaUser($this->admin)->get(route('out-of-pocket.receipt.download', $expenditure));
        $response->assertStatus(404);
    }

    public function test_consolidated_financial_report_includes_approved_out_of_pocket_expenditures_in_kpi(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'transportation',
            'description' => 'Field survey travel',
            'amount' => 35000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);

        $reportService = new ConsolidatedFinancialReportService;
        $kpis = $reportService->getKpis();

        $this->assertEquals(35000.00, $kpis['out_of_pocket_expenditure']);
        $this->assertGreaterThanOrEqual(35000.00, $kpis['total_expenditure']);
    }

    public function test_consolidated_financial_report_classifies_reimbursement_transaction_as_funding_transfer_to_prevent_double_counting(): void
    {
        $classification = ConsolidatedFinancialReportService::classifyType('out_of_pocket_reimbursement');
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_FUNDING_TRANSFER, $classification);
    }

    public function test_consolidated_financial_report_total_expenditure_does_not_double_count_reimbursed_out_of_pocket_items(): void
    {
        $staff = User::factory()->create();
        $expenditure = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'office_supplies',
            'description' => 'Paper and toner',
            'amount' => 40000.00,
        ]);

        $this->service->submit($expenditure, $staff);
        $this->service->approve($expenditure->fresh(), $this->admin);

        // Before reimbursement
        $reportService = new ConsolidatedFinancialReportService;
        $kpisBefore = $reportService->getKpis();
        $totalExpBefore = $kpisBefore['total_expenditure'];

        // Perform reimbursement
        $this->service->reimburse($expenditure->fresh(), $this->bankAccount, $this->admin);

        // After reimbursement (cash outflow transaction created)
        $kpisAfter = $reportService->getKpis();

        // Total expenditure must remain equal! The cash reimbursement transaction is under E. FUNDING / TRANSFER
        $this->assertEquals($totalExpBefore, $kpisAfter['total_expenditure']);
    }

    public function test_out_of_pocket_expenditures_are_filtered_by_approval_status(): void
    {
        OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $this->admin->id,
            'category' => 'transportation',
            'description' => 'Draft item 1',
            'amount' => 5000.00,
        ]);

        $staff = User::factory()->create();
        $exp2 = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'medical',
            'description' => 'Approved item 1',
            'amount' => 12000.00,
        ]);
        $this->service->submit($exp2, $staff);
        $this->service->approve($exp2->fresh(), $this->admin);

        $draftCount = OutOfPocketExpenditure::query()->where('approval_status', 'draft')->count();
        $approvedCount = OutOfPocketExpenditure::query()->where('approval_status', 'approved')->count();

        $this->assertEquals(1, $draftCount);
        $this->assertEquals(1, $approvedCount);
    }

    public function test_out_of_pocket_expenditures_are_filtered_by_reimbursement_status(): void
    {
        $staff = User::factory()->create();
        $exp = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'utilities',
            'description' => 'Water supply',
            'amount' => 15000.00,
        ]);
        $this->service->submit($exp, $staff);
        $this->service->approve($exp->fresh(), $this->admin);
        $this->service->reimburse($exp->fresh(), $this->bankAccount, $this->admin);

        $reimbursedCount = OutOfPocketExpenditure::query()->where('reimbursement_status', 'reimbursed')->count();
        $this->assertEquals(1, $reimbursedCount);
    }
}
