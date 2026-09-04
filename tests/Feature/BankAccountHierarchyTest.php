<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ConsolidatedFinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BankAccountHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Clear existing bank accounts for clean testing
        BankAccount::query()->forceDelete();
    }

    public function test_exactly_one_active_main_treasury_account_is_permitted()
    {
        // First main account should succeed
        $mainAccount1 = BankAccount::create([
            'account_name' => 'Main Account 1',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($mainAccount1->isMainAccount());

        // Second main account should fail
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only one central Main Account is permitted.');

        BankAccount::create([
            'account_name' => 'Main Account 2',
            'account_number' => '1234567891',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_dedicated_accounts_require_a_parent_account()
    {
        // Must have a Main Account first for the guard to enforce the hierarchy
        BankAccount::create([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'user_id' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dedicated accounts must be sub-accounts of the Main Treasury Account.');

        BankAccount::create([
            'account_name' => 'Dedicated 1',
            'account_number' => '0987654321',
            'parent_bank_account_id' => null, // Fails here — Main Account exists
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_main_can_receive_generic_deposit()
    {
        $mainAccount = BankAccount::create([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'user_id' => $this->user->id,
        ]);

        $transaction = Transaction::create([
            'bank_account_id' => $mainAccount->id,
            'type' => 'deposit',
            'amount' => 1000,
            'description' => 'Test deposit',
            'is_system' => false,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bank_account_id' => $mainAccount->id,
            'type' => 'deposit',
        ]);
    }

    public function test_dedicated_accounts_cannot_receive_generic_deposits()
    {
        $mainAccount = BankAccount::create([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'user_id' => $this->user->id,
        ]);

        $dedicatedAccount = BankAccount::create([
            'account_name' => 'Dedicated Account',
            'account_number' => '1234567891',
            'parent_bank_account_id' => $mainAccount->id,
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'user_id' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only the Main Treasury account can perform generic manual transactions.');

        Transaction::create([
            'bank_account_id' => $dedicatedAccount->id,
            'type' => 'deposit',
            'amount' => 1000,
            'description' => 'Test deposit',
            'is_system' => false,
        ]);
    }

    public function test_main_can_fund_dedicated_accounts_via_internal_transfer()
    {
        $mainAccount = BankAccount::create([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'ledger_balance' => 5000,
            'user_id' => $this->user->id,
        ]);

        $dedicatedAccount = BankAccount::create([
            'account_name' => 'Dedicated Account',
            'account_number' => '1234567891',
            'parent_bank_account_id' => $mainAccount->id,
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'user_id' => $this->user->id,
        ]);

        // Simulated manual transfer
        $transaction = Transaction::create([
            'bank_account_id' => $mainAccount->id,
            'destination_bank_account_id' => $dedicatedAccount->id,
            'type' => 'transfer',
            'amount' => 1000,
            'description' => 'Funding',
            'is_system' => false,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'bank_account_id' => $mainAccount->id,
            'destination_bank_account_id' => $dedicatedAccount->id,
            'type' => 'transfer',
        ]);

        $this->assertTrue($transaction->isInternalTransfer());
    }

    public function test_internal_transfers_do_not_inflate_consolidated_report_income_expenditure()
    {
        $mainAccount = BankAccount::create([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'ledger_balance' => 10000,
            'user_id' => $this->user->id,
        ]);

        $dedicatedAccount = BankAccount::create([
            'account_name' => 'Dedicated Account',
            'account_number' => '1234567891',
            'parent_bank_account_id' => $mainAccount->id,
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'user_id' => $this->user->id,
        ]);

        Transaction::create([
            'bank_account_id' => $mainAccount->id,
            'destination_bank_account_id' => $dedicatedAccount->id,
            'type' => 'transfer',
            'amount' => 5000,
            'description' => 'Funding',
            'is_system' => false,
        ]);

        // Make an actual expenditure from the dedicated account to see it correctly in the report
        Transaction::create([
            'bank_account_id' => $dedicatedAccount->id,
            'type' => 'out_of_pocket_reimbursement', // Note: this type acts as a transfer/reimbursement
            'amount' => 2000,
            'description' => 'Reimbursement',
            'is_system' => true,
        ]);

        $service = new ConsolidatedFinancialReportService;
        $kpis = $service->getKpis();

        // Income should be 0 (no deposits/credits in this test)
        $this->assertEquals(0, $kpis['income_receipts']);

        // Transfers should show up in internal_transfers KPI
        $this->assertEquals(5000, $kpis['internal_transfers']);

        // Total Expenditure doesn't include the internal transfer or out_of_pocket_reimbursement itself.
        // It's calculated from out_of_pocket_expenditures table which is mocked out in this test,
        // so it should be 0.
        $this->assertEquals(0, $kpis['total_expenditure']);
    }

    public function test_forged_requests_oop_reimbursement_debiting_main_are_blocked()
    {
        $mainAccount = BankAccount::create([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'parent_bank_account_id' => null,
            'usage' => BankAccount::USAGE_GENERAL,
            'user_id' => $this->user->id,
        ]);

        $this->assertFalse($mainAccount->canFundOutOfPocketReimbursement(100));
    }
}
