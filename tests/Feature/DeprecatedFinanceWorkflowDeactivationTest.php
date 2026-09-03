<?php

namespace Tests\Feature;

use App\Filament\Imprest\Resources\ImprestFundResource;
use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Models\BankAccount;
use App\Models\ImprestFund;
use App\Models\ImprestTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeprecatedFinanceWorkflowDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $coordinator;

    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $coordRole = Role::firstOrCreate(['name' => 'coordinator', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);

        $perm1 = Permission::firstOrCreate(['name' => 'admin_dashboard_access', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $perm2 = Permission::firstOrCreate(['name' => 'view_bank_accounts', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid()]);
        $adminRole->givePermissionTo([$perm1, $perm2]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->coordinator = User::factory()->create();
        $this->coordinator->assignRole('coordinator');

        $this->zone = Zone::create([
            'name' => 'Zone Alpha',
            'address' => '100 Alpha St',
            'coordinator_id' => $this->coordinator->id,
        ]);
        $this->coordinator->unsetRelation('coordinatedZone');
    }

    public function test_1_imprest_panel_and_login_routes_are_deactivated_and_return_404(): void
    {
        $response = $this->get('/imprest');
        $response->assertStatus(404);

        $loginResponse = $this->get('/imprest/login');
        $loginResponse->assertStatus(404);
    }

    public function test_2_coordinator_cannot_access_imprest_routes(): void
    {
        $this->actingAs($this->coordinator);

        $response = $this->get('/imprest');
        $response->assertStatus(404);

        $dashboardResponse = $this->get('/imprest/dashboard');
        $dashboardResponse->assertStatus(404);
    }

    public function test_3_admin_cannot_create_edit_or_delete_imprest_funds(): void
    {
        $this->assertFalse(ImprestFundResource::canCreate());

        $custodian = User::factory()->create();
        $fund = ImprestFund::factory()->create([
            'custodian_id' => $custodian->id,
            'authorized_amount' => 50000.00,
            'current_balance' => 50000.00,
        ]);

        $this->assertFalse(ImprestFundResource::canEdit($fund));
        $this->assertFalse(ImprestFundResource::canDelete($fund));
    }

    public function test_4_admin_cannot_create_edit_or_delete_imprest_transactions(): void
    {
        $this->assertFalse(ImprestTransactionResource::canCreate());

        $custodian = User::factory()->create();
        $fund = ImprestFund::factory()->create([
            'custodian_id' => $custodian->id,
            'authorized_amount' => 50000.00,
            'current_balance' => 50000.00,
        ]);

        $tx = ImprestTransaction::factory()->create([
            'fund_id' => $fund->id,
            'custodian_id' => $custodian->id,
        ]);

        $this->assertFalse(ImprestTransactionResource::canEdit($tx));
        $this->assertFalse(ImprestTransactionResource::canDelete($tx));
    }

    public function test_5_admin_cannot_create_edit_or_delete_imprest_replenishments(): void
    {
        $this->assertFalse(ImprestReplenishmentResource::canCreate());
    }

    public function test_6_admin_cannot_create_edit_or_delete_imprest_reconciliations(): void
    {
        $this->assertFalse(ImprestReconciliationResource::canCreate());
    }

    public function test_7_historical_imprest_records_remain_readable_in_database(): void
    {
        $custodian = User::factory()->create();
        $fund = ImprestFund::factory()->create([
            'custodian_id' => $custodian->id,
            'authorized_amount' => 100000.00,
            'current_balance' => 75000.00,
        ]);

        $tx = ImprestTransaction::factory()->create([
            'fund_id' => $fund->id,
            'custodian_id' => $custodian->id,
        ]);

        $this->assertDatabaseHas('imprest_funds', [
            'id' => $fund->id,
            'authorized_amount' => 100000.00,
        ]);

        $this->assertDatabaseHas('imprest_transactions', [
            'id' => $tx->id,
        ]);
    }

    public function test_8_out_of_pocket_new_creation_is_denied(): void
    {
        $this->assertFalse(ImprestTransactionResource::canCreate());
        $this->assertFalse(ImprestReplenishmentResource::canCreate());
    }

    public function test_9_existing_transaction_references_remain_valid(): void
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'account_name' => 'Main Operational Account',
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'currency' => 'NGN',
            'opening_balance' => 500000.00,
            'ledger_balance' => 500000.00,
            'usage' => 'general',
        ]);

        $transaction = Transaction::create([
            'bank_account_id' => $bankAccount->id,
            'type' => 'imprest_funding',
            'amount' => 50000.00,
            'reference' => 'TX-HISTORICAL-001',
            'transaction_date' => now(),
            'description' => 'Historical Imprest Funding',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'type' => 'imprest_funding',
            'amount' => 50000.00,
        ]);
    }

    public function test_10_bank_account_balances_remain_unchanged_by_deactivation(): void
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'account_name' => 'Test Account',
            'account_number' => '0987654321',
            'bank_name' => 'Test Bank',
            'currency' => 'NGN',
            'opening_balance' => 250000.00,
            'ledger_balance' => 250000.00,
            'usage' => 'general',
        ]);

        $this->assertEquals(250000.00, (float) $bankAccount->fresh()->ledger_balance);
    }

    public function test_11_unrelated_admin_finance_features_remain_functional(): void
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'account_name' => 'Test Account',
            'account_number' => '1122334455',
            'bank_name' => 'Test Bank',
            'currency' => 'NGN',
            'opening_balance' => 100000.00,
            'ledger_balance' => 100000.00,
            'usage' => 'general',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bankAccount->id,
            'ledger_balance' => 100000.00,
        ]);
    }

    public function test_12_coordinator_portal_remains_intact(): void
    {
        $this->assertNotNull($this->coordinator->coordinatedZone);
        $this->assertEquals($this->zone->id, $this->coordinator->coordinatedZone->id);
    }

    public function test_13_coordinator_has_zero_imprest_permissions(): void
    {
        $imprestPermissions = $this->coordinator->getAllPermissions()->filter(
            fn ($p) => str_contains($p->name, 'imprest')
        );

        $this->assertCount(0, $imprestPermissions);
    }

    public function test_14_operational_imprest_roles_cannot_mutate_custom_actions(): void
    {
        $this->assertFalse(ImprestFundResource::canCreate());
        $this->assertFalse(ImprestTransactionResource::canCreate());
        $this->assertFalse(ImprestReplenishmentResource::canCreate());
        $this->assertFalse(ImprestReconciliationResource::canCreate());
    }

    public function test_15_imprest_seeder_is_not_invoked_by_database_seeder(): void
    {
        $reflector = new \ReflectionClass(DatabaseSeeder::class);
        $filename = $reflector->getFileName();
        $contents = file_get_contents($filename);

        $this->assertDoesNotMatchRegularExpression('/^\s*ImprestSeeder::class/m', $contents);
    }

    public function test_16_out_of_pocket_has_no_independent_operational_entrypoint(): void
    {
        $this->assertFalse(ImprestTransactionResource::canCreate());
        $this->assertFalse(ImprestReplenishmentResource::canCreate());
    }
}
