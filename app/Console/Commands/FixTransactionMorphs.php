<?php

namespace App\Console\Commands;

use App\Models\Intervention;
use App\Models\Transaction;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use Illuminate\Console\Command;

class FixTransactionMorphs extends Command
{
    protected $signature = 'fix:transaction-morphs';

    protected $description = 'Restore missing polymorphic relationships for system-generated transactions';

    public function handle(): void
    {
        $this->info('Fixing Loan Repayments...');

        Transaction::where('reference', 'LIKE', 'REP-%')
            ->whereNull('transactionable_type')
            ->each(function ($transaction) {
                $uuidFragment = strtolower(substr($transaction->reference, 4));
                $repayment = WidowLoanRepayment::where('id', 'LIKE', "{$uuidFragment}%")->first();

                if ($repayment) {
                    $transaction->update([
                        'transactionable_type' => WidowLoanRepayment::class,
                        'transactionable_id' => $repayment->id,
                    ]);
                    $this->line("Fixed REP-{$uuidFragment}");
                }
            });

        $this->info('Fixing Interventions...');

        Transaction::where('reference', 'LIKE', 'INTV-%')
            ->whereNull('transactionable_type')
            ->each(function ($transaction) {
                $uuidFragment = strtolower(substr($transaction->reference, 5));
                $intervention = Intervention::where('id', 'LIKE', "{$uuidFragment}%")->first();

                if ($intervention) {
                    $transaction->update([
                        'transactionable_type' => Intervention::class,
                        'transactionable_id' => $intervention->id,
                    ]);
                    $this->line("Fixed INTV-{$uuidFragment}");
                }
            });

        $this->info('Fixing Disbursements...');

        Transaction::where('reference', 'LIKE', 'DISB-%')
            ->whereNull('transactionable_type')
            ->each(function ($transaction) {
                $uuidFragment = strtolower(substr($transaction->reference, 5));
                $loan = WidowLoan::where('id', 'LIKE', "{$uuidFragment}%")->first();

                if ($loan) {
                    $transaction->update([
                        'transactionable_type' => WidowLoan::class,
                        'transactionable_id' => $loan->id,
                    ]);
                    $this->line("Fixed DISB-{$uuidFragment}");
                }
            });

        $this->info('Morph relationships restored successfully!');
    }
}
