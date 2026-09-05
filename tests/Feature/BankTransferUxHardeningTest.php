<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BankTransferUxHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected BankAccount $sourceAccount;

    protected BankAccount $destinationAccount;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['uuid' => (string) \Illuminate\Support\Str::uuid()]
        );

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Create main/parent source account (zero balance initially)
        $this->sourceAccount = BankAccount::create([
            'account_name' => 'WRL Main Account',
            'account_number' => '1000000001',
            'bank_name' => 'WRL Bank',
            'user_id' => $this->admin->id,
            'usage' => BankAccount::USAGE_GENERAL,
        ]);
        $this->sourceAccount->updateQuietly(['ledger_balance' => 0.00, 'reserved_balance' => 0.00]);

        // Create sub/child account to serve as parent structure or destination
        $this->destinationAccount = BankAccount::create([
            'account_name' => 'Education Fees Account',
            'account_number' => '1000000002',
            'bank_name' => 'WRL Bank',
            'user_id' => $this->admin->id,
            'parent_bank_account_id' => $this->sourceAccount->id,
            'usage' => BankAccount::USAGE_EDUCATION,
        ]);
        $this->destinationAccount->updateQuietly(['ledger_balance' => 0.00, 'reserved_balance' => 0.00]);
    }

    public function test_a_zero_balance_source_disables_amount_and_shows_clear_helper_text(): void
    {
        $livewire = Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)
            ->mountTableAction('transferFunds', record: $this->sourceAccount);

        $form = $livewire->instance()->getMountedTableActionForm();
        $amountComponent = $form->getComponent('amount');

        expect($amountComponent)->not->toBeNull();
        expect($amountComponent->isDisabled())->toBeTrue();
    }

    public function test_b_transfer_greater_than_available_balance_is_rejected(): void
    {
        $this->sourceAccount->updateQuietly(['ledger_balance' => 100000.00, 'reserved_balance' => 0.00]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)
            ->callTableAction('transferFunds', record: $this->sourceAccount, data: [
                'destination_bank_account_id' => $this->destinationAccount->id,
                'amount' => 150000.00, // Exceeds 100k available balance
                'date' => now()->format('Y-m-d'),
                'reference' => 'TRF-TEST-001',
                'description' => 'Over-balance transfer attempt',
            ])
            ->assertHasTableActionErrors(['amount']);
    }

    public function test_c_exact_available_balance_transfer_succeeds(): void
    {
        $this->sourceAccount->updateQuietly(['ledger_balance' => 100000.00, 'reserved_balance' => 0.00]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)
            ->callTableAction('transferFunds', record: $this->sourceAccount, data: [
                'destination_bank_account_id' => $this->destinationAccount->id,
                'amount' => 100000.00, // Exact available balance
                'date' => now()->format('Y-m-d'),
                'reference' => 'TRF-TEST-002',
                'description' => 'Exact balance transfer',
            ])
            ->assertHasNoTableActionErrors();

        expect((float) $this->sourceAccount->fresh()->ledger_balance)->toBe(0.00);
        expect((float) $this->destinationAccount->fresh()->ledger_balance)->toBe(100000.00);
    }

    public function test_d_smaller_valid_transfer_succeeds(): void
    {
        $this->sourceAccount->updateQuietly(['ledger_balance' => 500000.00, 'reserved_balance' => 0.00]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)
            ->callTableAction('transferFunds', record: $this->sourceAccount, data: [
                'destination_bank_account_id' => $this->destinationAccount->id,
                'amount' => 250000.00,
                'date' => now()->format('Y-m-d'),
                'reference' => 'TRF-TEST-003',
                'description' => 'Transfer 250k of 500k',
            ])
            ->assertHasNoTableActionErrors();

        expect((float) $this->sourceAccount->fresh()->ledger_balance)->toBe(250000.00);
        expect((float) $this->destinationAccount->fresh()->ledger_balance)->toBe(250000.00);
    }

    public function test_e_failed_transfer_leaves_both_balances_unchanged(): void
    {
        $this->sourceAccount->updateQuietly(['ledger_balance' => 50000.00, 'reserved_balance' => 0.00]);
        $this->destinationAccount->updateQuietly(['ledger_balance' => 10000.00, 'reserved_balance' => 0.00]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)
            ->callTableAction('transferFunds', record: $this->sourceAccount, data: [
                'destination_bank_account_id' => $this->destinationAccount->id,
                'amount' => 90000.00, // Fails
                'date' => now()->format('Y-m-d'),
                'reference' => 'TRF-TEST-004',
                'description' => 'Failing transfer',
            ])
            ->assertHasTableActionErrors(['amount']);

        expect((float) $this->sourceAccount->fresh()->ledger_balance)->toBe(50000.00);
        expect((float) $this->destinationAccount->fresh()->ledger_balance)->toBe(10000.00);
    }

    public function test_f_valid_transfer_creates_expected_ledger_and_transaction_entries(): void
    {
        $this->sourceAccount->updateQuietly(['ledger_balance' => 300000.00, 'reserved_balance' => 0.00]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\BankAccounts\Pages\ListBankAccounts::class)
            ->callTableAction('transferFunds', record: $this->sourceAccount, data: [
                'destination_bank_account_id' => $this->destinationAccount->id,
                'amount' => 100000.00,
                'date' => now()->format('Y-m-d'),
                'reference' => 'TRF-TEST-005',
                'description' => 'Transfer for verification',
            ])
            ->assertHasNoTableActionErrors();

        expect(Transaction::where('reference', 'TRF-TEST-005')->count())->toBe(1);

        $txn = Transaction::where('reference', 'TRF-TEST-005')->first();
        expect($txn->bank_account_id)->toBe($this->sourceAccount->id);
        expect($txn->destination_bank_account_id)->toBe($this->destinationAccount->id);
        expect((float) $txn->amount)->toBe(100000.00);
        expect($txn->type)->toBe('transfer');
    }
}
