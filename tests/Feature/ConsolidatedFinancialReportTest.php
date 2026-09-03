<?php

namespace Tests\Feature;

use App\Filament\Pages\ConsolidatedFinancialReport;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ConsolidatedFinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedFinancialReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $superAdmin;

    protected User $coordinator;

    protected User $demoObserver;

    protected BankAccount $bankAccount;

    protected BankAccount $destBankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'secret',
            'mfa_confirmed_at' => now(),
            'mfa_enabled_at' => now(),
        ]);
        $this->admin->assignRole('admin');

        $this->superAdmin = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'secret',
            'mfa_confirmed_at' => now(),
            'mfa_enabled_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        session(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id]);

        $this->coordinator = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->demoObserver = User::factory()->create([
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->demoObserver->assignRole('demo_observer');

        $this->bankAccount = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Main Operating Account',
            'account_number' => '1002003004',
            'bank_name' => 'First Bank',
            'currency' => 'NGN',
            'opening_balance' => 1000000,
            'ledger_balance' => 1000000,
            'usage' => 'general',
            'status' => 'active',
        ]);

        $this->destBankAccount = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Reserve Account',
            'account_number' => '5006007008',
            'bank_name' => 'Zenith Bank',
            'currency' => 'NGN',
            'opening_balance' => 500000,
            'ledger_balance' => 500000,
            'usage' => 'general',
            'status' => 'active',
        ]);
    }

    public function test_1_admin_can_access_consolidated_financial_report_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
            ->get('/admin/consolidated-financial-report')
            ->assertSuccessful();
    }

    public function test_2_coordinator_is_denied_access_to_consolidated_financial_report(): void
    {
        $this->actingAs($this->coordinator)
            ->get('/admin/consolidated-financial-report')
            ->assertForbidden();
    }

    public function test_3_demo_observer_has_read_only_access(): void
    {
        $this->actingAs($this->demoObserver)
            ->get('/admin/consolidated-financial-report')
            ->assertSuccessful();
    }

    public function test_4_intervention_disbursement_classified_as_expenditure(): void
    {
        $tx = Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 50000,
            'type' => 'intervention',
            'description' => 'Medical assistance disbursement',
            'date' => now(),
            'is_system' => true,
        ]);

        $classification = ConsolidatedFinancialReportService::classifyType($tx->type);
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_EXPENDITURE, $classification);
    }

    public function test_5_education_fee_payment_classified_as_expenditure(): void
    {
        $tx = Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 120000,
            'type' => 'education_fee_payment',
            'description' => 'School tuition fee payment',
            'date' => now(),
            'is_system' => true,
        ]);

        $classification = ConsolidatedFinancialReportService::classifyType($tx->type);
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_EXPENDITURE, $classification);
    }

    public function test_6_widow_loan_disbursement_classified_as_asset_loan_movement(): void
    {
        $tx = Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 200000,
            'type' => 'loan_disbursement',
            'description' => 'Widow empowerment loan',
            'date' => now(),
            'is_system' => true,
        ]);

        $classification = ConsolidatedFinancialReportService::classifyType($tx->type);
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_LOAN_MOVEMENT, $classification);
    }

    public function test_7_widow_loan_repayment_classified_as_income_receipt(): void
    {
        $tx = Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 25000,
            'type' => 'loan_repayment',
            'description' => 'Loan repayment installment',
            'date' => now(),
            'is_system' => true,
        ]);

        $classification = ConsolidatedFinancialReportService::classifyType($tx->type);
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_INCOME_RECEIPT, $classification);
    }

    public function test_8_bank_transfer_classified_as_funding_transfer_and_excluded_from_expenditure(): void
    {
        $tx = Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'destination_bank_account_id' => $this->destBankAccount->id,
            'amount' => 100000,
            'type' => 'transfer',
            'description' => 'Internal transfer to reserve',
            'date' => now(),
            'is_system' => true,
        ]);

        $classification = ConsolidatedFinancialReportService::classifyType($tx->type, true);
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_FUNDING_TRANSFER, $classification);

        $service = app(ConsolidatedFinancialReportService::class);
        $kpis = $service->getKpis();

        $this->assertEquals(0.00, $kpis['total_expenditure']);
        $this->assertEquals(100000.00, $kpis['internal_transfers']);
    }

    public function test_9_historical_imprest_expense_classified_as_historical_deprecated(): void
    {
        $tx = Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 15000,
            'type' => 'imprest_expense',
            'description' => 'Historical petty cash expense',
            'date' => now(),
            'is_system' => true,
        ]);

        $classification = ConsolidatedFinancialReportService::classifyType($tx->type);
        $this->assertEquals(ConsolidatedFinancialReportService::CLASSIFICATION_HISTORICAL_DEPRECATED, $classification);
    }

    public function test_10_kpi_totals_match_filtered_dataset(): void
    {
        Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 50000,
            'type' => 'intervention',
            'description' => 'Intervention cash aid',
            'date' => now(),
            'is_system' => true,
        ]);

        Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 80000,
            'type' => 'deposit',
            'description' => 'Donor contribution receipt',
            'date' => now(),
            'is_system' => true,
        ]);

        $service = app(ConsolidatedFinancialReportService::class);
        $kpis = $service->getKpis();

        $this->assertEquals(50000.00, $kpis['total_expenditure']);
        $this->assertEquals(80000.00, $kpis['income_receipts']);
        $this->assertEquals(30000.00, $kpis['net_cash_movement']);
        $this->assertEquals(2, $kpis['transaction_count']);
    }

    public function test_11_expenditure_only_mode_filters_out_transfers_and_receipts(): void
    {
        Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 45000,
            'type' => 'intervention',
            'description' => 'Expense item',
            'date' => now(),
            'is_system' => true,
        ]);

        Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 100000,
            'type' => 'transfer',
            'description' => 'Bank transfer',
            'date' => now(),
            'is_system' => true,
        ]);

        $service = app(ConsolidatedFinancialReportService::class);
        $query = $service->getTransactionsQuery([], 'expenditure_only');

        $this->assertEquals(1, $query->count());
        $this->assertEquals('intervention', $query->first()->type);
    }

    public function test_12_csv_export_generates_streamed_response(): void
    {
        Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 30000,
            'type' => 'intervention',
            'description' => 'Export test payout',
            'date' => now(),
            'is_system' => true,
        ]);

        $service = app(ConsolidatedFinancialReportService::class);
        $response = $service->exportCsv();

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function test_13_report_does_not_mutate_database_state(): void
    {
        $initialBalance = $this->bankAccount->fresh()->ledger_balance;

        $service = app(ConsolidatedFinancialReportService::class);
        $service->getKpis();

        $this->assertEquals($initialBalance, $this->bankAccount->fresh()->ledger_balance);
    }

    public function test_14_admin_navigation_contains_consolidated_financial_report_item(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
            ->get('/admin');

        $response->assertSuccessful();
        $response->assertSee('Consolidated Financial Report');
        $response->assertSee('/admin/consolidated-financial-report');
    }

    public function test_15_coordinator_navigation_does_not_contain_consolidated_financial_report_item(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get('/admin');

        $response->assertDontSee('Consolidated Financial Report');
        $response->assertDontSee('/admin/consolidated-financial-report');
    }

    public function test_16_super_admin_can_access_consolidated_financial_report(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->superAdmin->id])
            ->get('/admin/consolidated-financial-report');

        $response->assertSuccessful();
    }

    public function test_17_page_registration_survives_optimize_clear(): void
    {
        $this->artisan('optimize:clear')->assertExitCode(0);

        $this->actingAs($this->admin);
        $this->assertTrue(ConsolidatedFinancialReport::canAccess());

        $this->actingAs($this->admin)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
            ->get('/admin/consolidated-financial-report')
            ->assertSuccessful();
    }

    public function test_18_pdf_export_generates_valid_download_response(): void
    {
        Transaction::create([
            'bank_account_id' => $this->bankAccount->id,
            'amount' => 75000,
            'type' => 'intervention',
            'description' => 'PDF export test intervention payout',
            'date' => now(),
            'is_system' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['mfa_verified_at' => time(), 'mfa_verified_user_id' => $this->admin->id])
            ->get('/admin/consolidated-financial-report/pdf?mode=all');

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename=consolidated-financial-report-', $response->headers->get('content-disposition'));
    }

    public function test_19_demo_observer_cannot_export_pdf(): void
    {
        $response = $this->actingAs($this->demoObserver)
            ->get('/admin/consolidated-financial-report/pdf');

        $response->assertForbidden();
    }

    public function test_20_coordinator_cannot_export_pdf(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get('/admin/consolidated-financial-report/pdf');

        $response->assertForbidden();
    }

    public function test_21_kpi_overview_widget_renders_stats(): void
    {
        \Livewire\Livewire::test(\App\Filament\Widgets\ConsolidatedFinancialOverviewWidget::class, [
            'filters' => [],
            'activeTab' => 'all',
        ])
            ->assertSee('Total Expenditure')
            ->assertSee('Income / Receipts')
            ->assertSee('Loan Disbursements')
            ->assertSee('Loan Repayments')
            ->assertSee('Internal Transfers')
            ->assertSee('Project Expenditure')
            ->assertSee('Education Expenditure')
            ->assertSee('Intervention Expenditure')
            ->assertSee('Historical Imprest')
            ->assertSee('Non-Cash Welfare')
            ->assertSee('Net Cash Movement')
            ->assertSee('Transaction Count');
    }
}
