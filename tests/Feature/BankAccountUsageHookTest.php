<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountUsageHookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_account_created_with_dedicated_usage_retains_that_usage(): void
    {
        $account = BankAccount::create([
            'account_name' => 'Intervention Dedicated Account',
            'account_number' => '1111222233',
            'user_id' => $this->user->id,
            'usage' => BankAccount::USAGE_INTERVENTION,
            'opening_balance' => 1000,
            'ledger_balance' => 1000,
        ]);

        $this->assertEquals(BankAccount::USAGE_INTERVENTION, $account->fresh()->usage);
    }

    public function test_account_created_without_usage_defaults_to_general(): void
    {
        $account = BankAccount::create([
            'account_name' => 'Default Main Account',
            'account_number' => '4444555566',
            'user_id' => $this->user->id,
            'opening_balance' => 2000,
            'ledger_balance' => 2000,
        ]);

        $this->assertEquals(BankAccount::USAGE_GENERAL, $account->fresh()->usage);
    }

    public function test_saving_existing_dedicated_account_does_not_change_its_usage(): void
    {
        $account = BankAccount::create([
            'account_name' => 'Education Account',
            'account_number' => '7777888899',
            'user_id' => $this->user->id,
            'usage' => BankAccount::USAGE_EDUCATION,
            'opening_balance' => 5000,
            'ledger_balance' => 5000,
        ]);

        $account->update(['ledger_balance' => 7500]);

        $this->assertEquals(BankAccount::USAGE_EDUCATION, $account->fresh()->usage);
        $this->assertEquals(7500.00, (float) $account->fresh()->ledger_balance);
    }

    public function test_child_account_requires_dedicated_usage(): void
    {
        $parent = BankAccount::create([
            'account_name' => 'Parent Main',
            'account_number' => '1000000001',
            'user_id' => $this->user->id,
            'usage' => BankAccount::USAGE_GENERAL,
        ]);

        $child = BankAccount::create([
            'account_name' => 'Valid Child',
            'account_number' => '1000000002',
            'user_id' => $this->user->id,
            'parent_bank_account_id' => $parent->id,
            'usage' => BankAccount::USAGE_GENERAL,
        ]);

        $this->assertEquals(BankAccount::USAGE_GENERAL, $child->usage);
    }
}
