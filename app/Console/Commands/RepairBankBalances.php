<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairBankBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:repair-bank-balances {--apply : Apply the calculated balance repairs to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and reproducibly repair bank account ledger balance mismatches based on canonical transaction movement.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('============================================================');
        $this->info('       GOF MIS REPRODUCIBLE BANK BALANCE REPAIR             ');
        $this->info('============================================================');
        $this->info('Mode: '.($apply ? 'APPLY (Writing changes to database)' : 'READ-ONLY DRY RUN (No database changes)'));
        $this->newLine();

        $accounts = BankAccount::whereNull('parent_bank_account_id')->get();
        $creditTypes = Transaction::getCreditTypes();
        $debitTypes = Transaction::getDebitTypes();

        $mismatches = [];
        $unknownTypeAccounts = [];

        foreach ($accounts as $account) {
            // Check for unrecognized transaction types linked to this account
            $unknownTypes = Transaction::where(function ($query) use ($account) {
                $query->where('bank_account_id', $account->id)
                    ->orWhere('destination_bank_account_id', $account->id);
            })
                ->whereNotIn('type', array_merge($creditTypes, $debitTypes))
                ->distinct()
                ->pluck('type')
                ->toArray();

            if (! empty($unknownTypes)) {
                $unknownTypeAccounts[] = [
                    'account' => $account,
                    'types' => $unknownTypes,
                ];

                continue;
            }

            $creditsOnAccount = (float) Transaction::where('bank_account_id', $account->id)
                ->whereIn('type', $creditTypes)
                ->sum('amount');

            $transfersIn = (float) Transaction::where('destination_bank_account_id', $account->id)
                ->sum('amount');

            $debitsOnAccount = (float) Transaction::where('bank_account_id', $account->id)
                ->whereIn('type', $debitTypes)
                ->sum('amount');

            $netTxSum = ($creditsOnAccount + $transfersIn) - $debitsOnAccount;
            $expectedLedger = (float) $account->opening_balance + $netTxSum;
            $storedLedger = (float) $account->ledger_balance;

            $difference = $expectedLedger - $storedLedger;

            if (abs($difference) > 0.01) {
                $mismatches[] = [
                    'account' => $account,
                    'old_balance' => $storedLedger,
                    'new_balance' => $expectedLedger,
                    'difference' => $difference,
                ];
            }
        }

        if (! empty($unknownTypeAccounts)) {
            $this->warn('UNKNOWN TRANSACTION TYPE(S) DETECTED:');
            foreach ($unknownTypeAccounts as $item) {
                $acc = $item['account'];
                $types = implode(', ', $item['types']);
                $this->error("  - Account [{$acc->account_name} / {$acc->account_number}]: UNKNOWN TRANSACTION TYPE ({$types})");
            }
            $this->warn('  Accounts with unknown transaction types were skipped from repair calculation.');
            $this->newLine();
        }

        if (empty($mismatches)) {
            $this->info('[OK] All bank account balances match calculated ledger totals. Zero changes required.');
            $this->info('============================================================');

            return Command::SUCCESS;
        }

        $tableData = array_map(function ($item) {
            $acc = $item['account'];

            return [
                'Account' => $acc->account_name.' ('.$acc->account_number.')',
                'Stored Ledger' => '₦'.number_format($item['old_balance'], 2),
                'Expected Ledger' => '₦'.number_format($item['new_balance'], 2),
                'Difference' => ($item['difference'] >= 0 ? '+' : '').'₦'.number_format($item['difference'], 2),
            ];
        }, $mismatches);

        $this->table(['Account', 'Stored Ledger', 'Expected Ledger', 'Difference'], $tableData);

        if (! $apply) {
            $this->warn('DRY RUN COMPLETE: Mismatches detected above. Run with --apply to commit repairs.');
            $this->info('============================================================');

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($mismatches) {
            foreach ($mismatches as $item) {
                /** @var BankAccount $acc */
                $acc = $item['account'];
                $oldVal = $item['old_balance'];
                $newVal = $item['new_balance'];
                $diff = $item['difference'];

                $acc->update(['ledger_balance' => $newVal]);

                if (class_exists(Activity::class)) {
                    Activity::create([
                        'log_name' => 'financial_repair',
                        'description' => "Repaired bank balance for {$acc->account_name} ({$acc->account_number})",
                        'subject_type' => BankAccount::class,
                        'subject_id' => $acc->id,
                        'event' => 'reconciled_balance_repair',
                        'properties' => [
                            'old_balance' => $oldVal,
                            'new_balance' => $newVal,
                            'difference' => $diff,
                            'command' => 'finance:repair-bank-balances',
                        ],
                    ]);
                }
            }
        });

        $repairedCount = count($mismatches);
        $this->info("[SUCCESS] Applied balance repairs to {$repairedCount} bank account(s) inside a DB transaction.");
        $this->info('============================================================');

        return Command::SUCCESS;
    }
}
