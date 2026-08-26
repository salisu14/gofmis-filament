<?php

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::first() ?? User::factory()->create();
});

test('1. read-only repair changes nothing on corrupted balance', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Corrupted Test Acc 1',
        'account_number' => '8880001111',
        'opening_balance' => 100000.00,
        'ledger_balance' => 50000.00, // Corrupted: missing 50k
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Artisan::call('finance:repair-bank-balances');
    $output = Artisan::output();

    expect($output)->toContain('READ-ONLY DRY RUN')
        ->and($output)->toContain('DRY RUN COMPLETE')
        ->and((float) $account->fresh()->ledger_balance)->toBe(50000.00);
});

test('2. --apply corrects a deliberately corrupted balance inside DB transaction', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Corrupted Test Acc 2',
        'account_number' => '8880001112',
        'opening_balance' => 100000.00,
        'ledger_balance' => 50000.00, // Corrupted
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Transaction::create([
        'bank_account_id' => $account->id,
        'amount' => 20000.00,
        'type' => 'deposit',
        'reference' => 'DEP-TST-02',
        'description' => 'Test deposit',
        'date' => now(),
        'is_system' => true, // bypass postToBank to simulate manual DB corruption
    ]);

    // Expected = 100,000 opening + 20,000 deposit = 120,000.00
    expect((float) $account->fresh()->ledger_balance)->toBe(50000.00);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);
    $output = Artisan::output();

    expect($output)->toContain('Applied balance repairs to 1 bank account(s)')
        ->and((float) $account->fresh()->ledger_balance)->toBe(120000.00);
});

test('3. second --apply is idempotent and makes zero changes', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Corrupted Test Acc 3',
        'account_number' => '8880001113',
        'opening_balance' => 100000.00,
        'ledger_balance' => 50000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);
    expect((float) $account->fresh()->ledger_balance)->toBe(100000.00);

    // Second run
    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);
    $output = Artisan::output();

    expect($output)->toContain('Zero changes required');
});

test('4. correct account is untouched during repair', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Correct Test Acc',
        'account_number' => '8880001114',
        'opening_balance' => 75000.00,
        'ledger_balance' => 75000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);

    expect((float) $account->fresh()->ledger_balance)->toBe(75000.00);
});

test('5. opening balance is included in ledger reconstruction', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Opening Balance Acc',
        'account_number' => '8880001115',
        'opening_balance' => 250000.00,
        'ledger_balance' => 0.00, // Corrupted to 0
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);

    expect((float) $account->fresh()->ledger_balance)->toBe(250000.00);
});

test('6. incoming transfers are included once and outgoing transfers debited once', function () {
    $sourceAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Source Acc',
        'account_number' => '8880001116',
        'opening_balance' => 500000.00,
        'ledger_balance' => 500000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    $destAccount = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Dest Acc',
        'account_number' => '8880001117',
        'opening_balance' => 100000.00,
        'ledger_balance' => 100000.00,
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Transaction::create([
        'bank_account_id' => $sourceAccount->id,
        'destination_bank_account_id' => $destAccount->id,
        'amount' => 50000.00,
        'type' => 'transfer',
        'reference' => 'TRF-TST-06',
        'description' => 'Test transfer',
        'date' => now(),
        'is_system' => true,
    ]);

    // Corrupt both balances
    $sourceAccount->update(['ledger_balance' => 0.00]);
    $destAccount->update(['ledger_balance' => 0.00]);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);

    // Source expected = 500,000 opening - 50,000 transfer out = 450,000
    // Dest expected = 100,000 opening + 50,000 transfer in = 150,000
    expect((float) $sourceAccount->fresh()->ledger_balance)->toBe(450000.00)
        ->and((float) $destAccount->fresh()->ledger_balance)->toBe(150000.00);
});

test('7. reserved balance does not alter ledger reconstruction', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Reserved Acc',
        'account_number' => '8880001118',
        'opening_balance' => 200000.00,
        'ledger_balance' => 100000.00, // Corrupted
        'reserved_balance' => 50000.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);

    // Ledger balance should be restored to 200,000 (opening_balance), ignoring reserved_balance
    expect((float) $account->fresh()->ledger_balance)->toBe(200000.00)
        ->and((float) $account->fresh()->reserved_balance)->toBe(50000.00);
});

test('8. unknown transaction type is reported and account flagged without guessing', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Unknown Type Acc',
        'account_number' => '8880001119',
        'opening_balance' => 100000.00,
        'ledger_balance' => 50000.00, // Corrupted
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    // Create a transaction with an unknown/unclassified type
    DB::table('transactions')->insert([
        'id' => (string) Str::uuid(),
        'bank_account_id' => $account->id,
        'amount' => 10000.00,
        'type' => 'alien_crypto_mint',
        'reference' => 'TXN-UNKNOWN-01',
        'description' => 'Unknown transaction',
        'date' => now(),
        'is_system' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);
    $output = Artisan::output();

    expect($output)->toContain('UNKNOWN TRANSACTION TYPE')
        ->and($output)->toContain('alien_crypto_mint')
        // Balance remains un-updated (50000) because repairing was skipped
        ->and((float) $account->fresh()->ledger_balance)->toBe(50000.00);
});

test('9. finance:reconcile is clean after repair', function () {
    $account = BankAccount::create([
        'user_id' => $this->admin->id,
        'account_name' => 'Corrupted Reconcile Acc',
        'account_number' => '8880001120',
        'opening_balance' => 100000.00,
        'ledger_balance' => 30000.00, // Corrupted
        'reserved_balance' => 0.00,
        'usage' => BankAccount::USAGE_GENERAL,
    ]);

    Transaction::create([
        'bank_account_id' => $account->id,
        'amount' => 20000.00,
        'type' => 'deposit',
        'reference' => 'DEP-REC-09',
        'description' => 'Test deposit',
        'date' => now(),
        'is_system' => true,
    ]);

    // Prior to repair, reconcile reports issue (expected 120,000 vs stored 30,000)
    Artisan::call('finance:reconcile');
    expect(Artisan::output())->toContain('8880001120');

    // Repair
    Artisan::call('finance:repair-bank-balances', ['--apply' => true]);

    // Post-repair, reconcile is clean
    Artisan::call('finance:reconcile');
    expect(Artisan::output())->toContain('passed integrity and reconciliation checks cleanly');
});
