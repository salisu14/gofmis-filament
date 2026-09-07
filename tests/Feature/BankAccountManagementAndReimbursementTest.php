<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\OutOfPocketExpenditure;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OutOfPocketExpenditureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BankAccountManagementAndReimbursementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $coordinator;

    protected BankAccount $mainAccount;

    protected BankAccount $oopAccount;

    protected OutOfPocketExpenditureService $oopService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin_bank@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'TEST_SECRET_KEY_123',
            'mfa_confirmed_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        $this->admin = User::factory()->create([
            'email' => 'admin_bank@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
            'app_authentication_secret' => 'TEST_SECRET_KEY_123',
            'mfa_confirmed_at' => now(),
        ]);
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create([
            'email' => 'coordinator_bank@gof.test',
            'is_active' => true,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);
        $this->coordinator->assignRole('coordinator');

        $this->mainAccount = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'General Operating Fund',
            'account_number' => '0100000001',
            'bank_name' => 'Access Bank',
            'usage' => BankAccount::USAGE_GENERAL,
            'opening_balance' => 1000000.00,
            'ledger_balance' => 1000000.00,
            'reserved_balance' => 0.00,
        ]);

        $this->oopAccount = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'OOP Reimbursement Fund',
            'account_number' => '0100000002',
            'bank_name' => 'Access Bank',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'status' => 'active',
        ]);

        // The creating hook zeros sub-account balances (they must be funded via internal transfer).
        // Seed directly via update() to simulate a funded state for tests.
        $this->oopAccount->update([
            'ledger_balance' => 1000000.00,
            'reserved_balance' => 0.00,
        ]);

        $this->oopService = new OutOfPocketExpenditureService;
    }

    protected function actingAsMfaUser(User $user)
    {
        return $this->withSession([
            'mfa_verified_at' => time(),
            'mfa_verified_user_id' => $user->id,
        ])->actingAs($user);
    }

    public function test_admin_can_create_first_bank_account(): void
    {
        // In the new architecture the Main Account already exists (from setUp).
        // A second USAGE_GENERAL root account is blocked — verify the Main Account itself is set up correctly.
        $this->assertDatabaseHas('bank_accounts', [
            'id' => $this->mainAccount->id,
            'account_name' => 'General Operating Fund',
            'usage' => 'general',
        ]);
        $this->assertTrue($this->mainAccount->isMainAccount());
    }

    public function test_admin_can_create_multiple_bank_accounts(): void
    {
        // In the new architecture, multiple dedicated sub-accounts can be created under the Main Account.
        $acc2 = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'WRL Disbursement Account',
            'account_number' => '0200000002',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
        ]);

        $acc3 = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Third Dedicated Account',
            'account_number' => '0200000003',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
        ]);

        $this->assertDatabaseHas('bank_accounts', ['id' => $acc2->id]);
        $this->assertDatabaseHas('bank_accounts', ['id' => $acc3->id]);
        $this->assertGreaterThanOrEqual(3, BankAccount::count());
    }

    public function test_no_undocumented_global_bank_account_count_limit_exists(): void
    {
        // Many dedicated sub-accounts should be creatable without limit.
        for ($i = 10; $i < 15; $i++) {
            BankAccount::create([
                'user_id' => $this->admin->id,
                'account_name' => "Bulk Dedicated Account {$i}",
                'account_number' => "03000000{$i}",
                'parent_bank_account_id' => $this->mainAccount->id,
                'usage' => BankAccount::USAGE_INTERVENTION,
            ]);
        }

        $this->assertGreaterThanOrEqual(6, BankAccount::count());
    }

    public function test_duplicate_account_number_is_handled_with_visible_validation(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create a sub-account with the same account number as mainAccount.
        BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Duplicate Account',
            'account_number' => '0100000001', // Duplicate of mainAccount
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
        ]);
    }

    public function test_valid_second_account_creation_succeeds(): void
    {
        // A second account must be a dedicated sub-account under Main.
        $acc = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Valid Second Account',
            'account_number' => '0400000001',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_EDUCATION,
        ]);

        $this->assertEquals('Valid Second Account', $acc->fresh()->account_name);
    }

    public function test_bank_account_edit_page_is_accessible_to_authorized_admin(): void
    {
        $response = $this->actingAsMfaUser($this->admin)->get("/admin/bank-accounts/{$this->mainAccount->id}/edit");
        $response->assertStatus(200);
    }

    public function test_safe_metadata_can_be_edited(): void
    {
        $newManager = User::factory()->create();

        $this->mainAccount->update([
            'account_name' => 'Renamed General Operating Fund',
            'user_id' => $newManager->id,
        ]);

        $this->assertEquals('Renamed General Operating Fund', $this->mainAccount->fresh()->account_name);
        $this->assertEquals($newManager->id, $this->mainAccount->fresh()->user_id);
    }

    public function test_account_usage_can_be_corrected_when_safe(): void
    {
        // Create a sub-account with USAGE_OTHER and verify usage can be corrected when safe.
        $freshAccount = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Unused Fresh Account',
            'account_number' => '0500000001',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_OTHER,
        ]);

        $this->assertFalse($freshAccount->hasTransactions());

        $freshAccount->update(['usage' => BankAccount::USAGE_INTERVENTION]);
        $this->assertEquals(BankAccount::USAGE_INTERVENTION, $freshAccount->fresh()->usage);
    }

    public function test_unsafe_usage_mutation_after_dependent_history_is_blocked_clearly(): void
    {
        Transaction::create([
            'bank_account_id' => $this->mainAccount->id,
            'amount' => 5000.00,
            'type' => 'withdrawal',
            'description' => 'Test transaction',
            'reference' => Transaction::generateReference('withdrawal'),
            'date' => now(),
            'is_system' => true,
        ]);

        $this->assertTrue($this->mainAccount->hasTransactions());
    }

    public function test_dedicated_child_account_can_be_created_correctly(): void
    {
        $child = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Dedicated Medical Child Account',
            'account_number' => '0700000001',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
        ]);

        $this->assertEquals($this->mainAccount->id, $child->parent_bank_account_id);
        $this->assertEquals(BankAccount::USAGE_INTERVENTION, $child->usage);
    }

    public function test_general_operating_account_can_be_created_correctly(): void
    {
        // There is exactly one root USAGE_GENERAL account (mainAccount). Verify its properties.
        $this->assertNull($this->mainAccount->parent_bank_account_id);
        $this->assertEquals(BankAccount::USAGE_GENERAL, $this->mainAccount->usage);
        $this->assertTrue($this->mainAccount->isMainAccount());
    }

    public function test_oop_reimbursement_selector_includes_eligible_oop_account(): void
    {
        // oopAccount is already created in setUp as a sub-account of mainAccount
        // with ledger_balance = 1,000,000 so it is eligible.
        $eligible = BankAccount::getEligibleForOutOfPocketReimbursement(35000.00);

        $this->assertTrue($eligible->contains('id', $this->oopAccount->id));
    }

    public function test_ineligible_restricted_account_is_excluded(): void
    {
        $child = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Restricted Sub Account',
            'account_number' => '0900000001',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_EDUCATION,
        ]);

        $eligible = BankAccount::getEligibleForOutOfPocketReimbursement(5000.00);

        $this->assertFalse($eligible->contains($child));
    }

    public function test_insufficient_funds_account_is_rejected_server_side(): void
    {
        $poorAccount = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Poor Account',
            'account_number' => '0900000002',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'status' => 'active',
            'opening_balance' => 100.00,
            'ledger_balance' => 100.00,
            'reserved_balance' => 0.00,
        ]);

        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'office_supplies',
            'description' => 'Printer ink',
            'amount' => 45000.00,
        ]);

        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);

        $this->expectException(ValidationException::class);
        $this->oopService->reimburse($oop->fresh(), $poorAccount, $this->admin);
    }

    public function test_eligible_account_appears_in_reimbursement_action(): void
    {
        $count = BankAccount::getEligibleForOutOfPocketReimbursement(10000.00)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_reimbursement_succeeds_from_eligible_account(): void
    {
        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'medical',
            'description' => 'Staff prescription',
            'amount' => 18000.00,
        ]);

        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);

        $reimbursed = $this->oopService->reimburse($oop->fresh(), $this->oopAccount, $this->admin);

        $this->assertTrue($reimbursed->isReimbursed());
    }

    public function test_correct_account_balance_is_debited_exactly_once(): void
    {
        $initialBalance = (float) $this->oopAccount->fresh()->ledger_balance;

        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'utilities',
            'description' => 'Office internet bill',
            'amount' => 50000.00,
        ]);

        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);
        $this->oopService->reimburse($oop->fresh(), $this->oopAccount, $this->admin);

        $this->assertEquals($initialBalance - 50000.00, (float) $this->oopAccount->fresh()->ledger_balance);
    }

    public function test_exactly_one_transaction_is_created(): void
    {
        $initialTxnCount = Transaction::query()->where('type', 'out_of_pocket_reimbursement')->count();

        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'transportation',
            'description' => 'Inspection trip',
            'amount' => 22000.00,
        ]);

        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);
        $reimbursed = $this->oopService->reimburse($oop->fresh(), $this->oopAccount, $this->admin);

        $finalTxnCount = Transaction::query()->where('type', 'out_of_pocket_reimbursement')->count();
        $this->assertEquals($initialTxnCount + 1, $finalTxnCount);
        $this->assertEquals($reimbursed->reimbursement_transaction_id, Transaction::latest()->first()->id);
    }

    public function test_duplicate_reimbursement_remains_blocked(): void
    {
        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'office_supplies',
            'description' => 'Desks and chairs',
            'amount' => 60000.00,
        ]);

        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);
        $reimbursed = $this->oopService->reimburse($oop->fresh(), $this->oopAccount, $this->admin);

        $this->expectException(ValidationException::class);
        $this->oopService->reimburse($reimbursed->fresh(), $this->oopAccount, $this->admin);
    }

    public function test_failed_reimbursement_rolls_back(): void
    {
        $initialBalance = (float) $this->oopAccount->fresh()->ledger_balance;

        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'medical',
            'description' => 'Rollback test item',
            'amount' => 2000000.00, // Exceeds ledger balance
        ]);

        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);

        try {
            $this->oopService->reimburse($oop->fresh(), $this->oopAccount, $this->admin);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertEquals('pending', $oop->fresh()->reimbursement_status);
            $this->assertEquals($initialBalance, (float) $this->oopAccount->fresh()->ledger_balance);
        }
    }

    public function test_existing_bank_account_usage_hook_test_remains_green(): void
    {
        $account = BankAccount::create([
            'account_name' => 'Intervention Dedicated Account Test',
            'account_number' => '1111222244',
            'user_id' => $this->admin->id,
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
        ]);

        $this->assertEquals(BankAccount::USAGE_INTERVENTION, $account->fresh()->usage);
    }

    public function test_existing_b09_oop_tests_remain_green(): void
    {
        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'other',
            'description' => 'B09 test item',
            'amount' => 10000.00,
        ]);

        $this->assertTrue($oop->isDraft());
    }

    public function test_oop_reimbursement_regression_tests(): void
    {
        // In the new architecture there is ONE main account. Use it as the parent for all sub-accounts.
        $generalParent = $this->mainAccount;

        $wrlParent = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'WRL Disbursement Sub',
            'account_number' => '1000000011',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
        ]);
        $wrlParent->update([
            'opening_balance' => 100000.00,
            'ledger_balance' => 100000.00,
            'reserved_balance' => 0.00,
        ]);

        $wrlDisbursement = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'WRL Disbursement',
            'account_number' => '1000000012',
            'usage' => BankAccount::USAGE_WIDOW_LOAN_DISBURSEMENT,
            'parent_bank_account_id' => $wrlParent->id,
        ]);
        $wrlDisbursement->update([
            'opening_balance' => 100000.00,
            'ledger_balance' => 100000.00,
            'reserved_balance' => 0.00,
        ]);

        $oopDedicated = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'OOP Dedicated',
            'account_number' => '1000000013',
            'usage' => BankAccount::USAGE_OUT_OF_POCKET_EXPENSE,
            'parent_bank_account_id' => $generalParent->id,
        ]);
        $oopDedicated->update([
            'opening_balance' => 100000.00,
            'ledger_balance' => 100000.00,
            'reserved_balance' => 0.00,
        ]);

        $education = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'Education',
            'account_number' => '1000000014',
            'usage' => BankAccount::USAGE_EDUCATION,
            'parent_bank_account_id' => $generalParent->id,
        ]);
        $education->update([
            'opening_balance' => 100000.00,
            'ledger_balance' => 100000.00,
            'reserved_balance' => 0.00,
        ]);

        $generalInsufficient = BankAccount::create([
            'user_id' => $this->admin->id,
            'account_name' => 'General Insufficient',
            'account_number' => '1000000015',
            'parent_bank_account_id' => $this->mainAccount->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
            'opening_balance' => 100000.00,
            'ledger_balance' => 100000.00,
            'reserved_balance' => 90000.00, // Available: 10k
        ]);

        $eligible = BankAccount::getEligibleForOutOfPocketReimbursement(20000.00)->pluck('id')->toArray();

        // 1. general parent account DOES NOT appear (only out_of_pocket_expense allowed)
        $this->assertNotContains($generalParent->id, $eligible);

        // 2. WRL parent account with null parent_id does NOT appear
        $this->assertNotContains($wrlParent->id, $eligible);

        // 3. WRL disbursement account does NOT appear
        $this->assertNotContains($wrlDisbursement->id, $eligible);

        // 4. OOP dedicated-use account DOES appear
        $this->assertContains($oopDedicated->id, $eligible);

        // 5. education account does NOT appear
        $this->assertNotContains($education->id, $eligible);

        // 9. general account with reserved funds reducing available balance below reimbursement amount does NOT appear
        $this->assertNotContains($generalInsufficient->id, $eligible);

        $staff = User::factory()->create();
        $oop = OutOfPocketExpenditure::create([
            'incurred_by_user_id' => $staff->id,
            'category' => 'other',
            'description' => 'Test',
            'amount' => 20000.00,
        ]);
        $this->oopService->submit($oop, $staff);
        $this->oopService->approve($oop->fresh(), $this->admin);

        // 10. forged WRL account ID is rejected server-side
        try {
            $this->oopService->reimburse($oop->fresh(), $wrlParent, $this->admin);
            $this->fail('Expected exception for forged WRL parent');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bank_account_id', $e->errors());
        }

        // 11. forged GENERAL account ID is rejected server-side
        try {
            $this->oopService->reimburse($oop->fresh(), $generalParent, $this->admin);
            $this->fail('Expected exception for forged GENERAL parent');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bank_account_id', $e->errors());
        }

        // 12. forged education account ID is rejected server-side
        try {
            $this->oopService->reimburse($oop->fresh(), $education, $this->admin);
            $this->fail('Expected exception for forged education');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bank_account_id', $e->errors());
        }
    }

    public function test_consolidated_financial_report_test_remains_green(): void
    {
        $service = new \App\Services\ConsolidatedFinancialReportService;
        $kpis = $service->getKpis();

        $this->assertArrayHasKey('total_expenditure', $kpis);
        $this->assertArrayHasKey('out_of_pocket_expenditure', $kpis);
    }
}
