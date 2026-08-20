<?php

namespace App\Console\Commands;

use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\EducationFeeInvoice;
use App\Models\ImprestFund;
use App\Models\Project;
use App\Models\WidowLoan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReconcileFinance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:reconcile {--details} {--export=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform unified read-only diagnostic audit for bank accounts, widow loans, imprest, education fees, and projects';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('          GOF MIS UNIFIED FINANCIAL RECONCILIATION          ');
        $this->info('============================================================');

        $inconsistencies = [];
        $modulesChecked = 0;
        $totalIssues = 0;

        // 1. BANK ACCOUNTS AUDIT
        $modulesChecked++;
        $bankAccounts = BankAccount::all();
        foreach ($bankAccounts as $account) {
            $ledger = (float) $account->ledger_balance;
            $reserved = (float) ($account->reserved_balance ?? 0);
            $available = $ledger - $reserved;

            if ($ledger < 0) {
                $inconsistencies[] = [
                    'module' => 'Bank Account',
                    'id' => $account->id,
                    'reference' => $account->display_name ?? $account->account_number,
                    'issue' => 'Negative ledger balance (₦'.number_format($ledger, 2).')',
                ];
                $totalIssues++;
            }

            if ($reserved > $ledger) {
                $inconsistencies[] = [
                    'module' => 'Bank Account',
                    'id' => $account->id,
                    'reference' => $account->display_name ?? $account->account_number,
                    'issue' => "Reserved balance (₦{$reserved}) exceeds ledger balance (₦{$ledger})",
                ];
                $totalIssues++;
            }

            // Verify transaction sum against ledger balance if transactions exist
            if ($account->transactions()->exists()) {
                $txSum = (float) $account->transactions()
                    ->selectRaw("SUM(CASE WHEN type = 'credit' THEN amount WHEN type = 'debit' THEN -amount ELSE 0 END) as net_sum")
                    ->value('net_sum');

                $expectedLedger = (float) $account->opening_balance + $txSum;
                if (! $account->isSubAccount() && abs($expectedLedger - $ledger) > 0.05) {
                    $inconsistencies[] = [
                        'module' => 'Bank Account',
                        'id' => $account->id,
                        'reference' => $account->display_name ?? $account->account_number,
                        'issue' => 'Ledger balance (₦'.number_format($ledger, 2).') mismatch with transaction sum (₦'.number_format($expectedLedger, 2).')',
                    ];
                    $totalIssues++;
                }
            }
        }

        // 2. WIDOW LOANS AUDIT
        $modulesChecked++;
        $loans = WidowLoan::all();
        foreach ($loans as $loan) {
            $totalPaid = (float) $loan->total_paid;
            $totalPayable = (float) $loan->total_payable;
            $outstanding = (float) $loan->outstanding_balance;

            // Invariant: total_paid + outstanding_balance == total_payable (except written-off)
            if ($loan->status !== WidowLoanStatus::WRITTEN_OFF) {
                if (abs(($totalPaid + $outstanding) - $totalPayable) > 0.05) {
                    $inconsistencies[] = [
                        'module' => 'Widow Loan',
                        'id' => $loan->id,
                        'reference' => "Loan #{$loan->id} ({$loan->widow?->full_name})",
                        'issue' => 'Financial invariant failure: paid (₦'.number_format($totalPaid, 2).') + outstanding (₦'.number_format($outstanding, 2).') != total payable (₦'.number_format($totalPayable, 2).')',
                    ];
                    $totalIssues++;
                }
            }

            if ($totalPaid > $totalPayable) {
                $inconsistencies[] = [
                    'module' => 'Widow Loan',
                    'id' => $loan->id,
                    'reference' => "Loan #{$loan->id} ({$loan->widow?->full_name})",
                    'issue' => 'Overpayment detected: total paid (₦'.number_format($totalPaid, 2).') exceeds payable (₦'.number_format($totalPayable, 2).')',
                ];
                $totalIssues++;
            }

            if ($loan->status === WidowLoanStatus::COMPLETED && $outstanding > 0) {
                $inconsistencies[] = [
                    'module' => 'Widow Loan',
                    'id' => $loan->id,
                    'reference' => "Loan #{$loan->id} ({$loan->widow?->full_name})",
                    'issue' => 'Completed loan has non-zero outstanding balance (₦'.number_format($outstanding, 2).')',
                ];
                $totalIssues++;
            }
        }

        // 3. IMPREST FUNDS AUDIT
        $modulesChecked++;
        $imprestFunds = ImprestFund::all();
        foreach ($imprestFunds as $fund) {
            $balance = (float) $fund->current_balance;
            if ($balance < 0) {
                $inconsistencies[] = [
                    'module' => 'Imprest Fund',
                    'id' => $fund->id,
                    'reference' => $fund->name ?? "Fund #{$fund->id}",
                    'issue' => 'Negative imprest fund balance (₦'.number_format($balance, 2).')',
                ];
                $totalIssues++;
            }
        }

        // 4. EDUCATION FEE INVOICES AUDIT
        $modulesChecked++;
        $invoices = EducationFeeInvoice::all();
        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->amount;
            $paid = (float) $invoice->total_paid;
            $balance = (float) $invoice->balance;

            if ($paid > $amount) {
                $inconsistencies[] = [
                    'module' => 'Education Fee Invoice',
                    'id' => $invoice->id,
                    'reference' => "Invoice #{$invoice->invoice_number}",
                    'issue' => 'Overpaid invoice: paid (₦'.number_format($paid, 2).') exceeds amount (₦'.number_format($amount, 2).')',
                ];
                $totalIssues++;
            }

            if (abs($balance - ($amount - $paid)) > 0.05 && ! $invoice->isVoided()) {
                $inconsistencies[] = [
                    'module' => 'Education Fee Invoice',
                    'id' => $invoice->id,
                    'reference' => "Invoice #{$invoice->invoice_number}",
                    'issue' => 'Invoice balance (₦'.number_format($balance, 2).') mismatch with calculated balance (₦'.number_format($amount - $paid, 2).')',
                ];
                $totalIssues++;
            }
        }

        // 5. PROJECTS AUDIT
        $modulesChecked++;
        $projects = Project::all();
        foreach ($projects as $project) {
            $allocated = (float) $project->budget_allocated;
            $spent = (float) $project->budget_spent;
            $remaining = (float) $project->budget_remaining;

            if (abs($remaining - ($allocated - $spent)) > 0.05) {
                $inconsistencies[] = [
                    'module' => 'Project',
                    'id' => $project->id,
                    'reference' => $project->name,
                    'issue' => 'Budget remaining (₦'.number_format($remaining, 2).') mismatch with calculated (₦'.number_format($allocated - $spent, 2).')',
                ];
                $totalIssues++;
            }

            if ($spent > $allocated && ! $project->is_over_budget) {
                $inconsistencies[] = [
                    'module' => 'Project',
                    'id' => $project->id,
                    'reference' => $project->name,
                    'issue' => 'Project spent exceeds budget but is_over_budget flag is false',
                ];
                $totalIssues++;
            }
        }

        $this->info("Financial modules audited: {$modulesChecked}. Total issues detected: {$totalIssues}.");

        if (! empty($inconsistencies)) {
            $tableData = [];
            foreach ($inconsistencies as $row) {
                $tableData[] = [
                    $row['module'],
                    $row['id'],
                    $row['reference'],
                    $row['issue'],
                ];
            }

            $this->table(['Module', 'ID', 'Reference', 'Issue Description'], $tableData);

            if ($exportPath = $this->option('export')) {
                Storage::disk('local')->put($exportPath, json_encode($inconsistencies, JSON_PRETTY_PRINT));
                $this->info('Diagnostic results exported to: '.Storage::disk('local')->path($exportPath));
            }
        } else {
            $this->info('[OK] All financial modules passed integrity and reconciliation checks cleanly.');
        }

        $this->info('============================================================');

        return Command::SUCCESS;
    }
}
