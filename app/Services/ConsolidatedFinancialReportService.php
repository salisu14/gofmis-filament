<?php

namespace App\Services;

use App\Models\ProjectExpense;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsolidatedFinancialReportService
{
    public const CLASSIFICATION_EXPENDITURE = 'A. EXPENDITURE';

    public const CLASSIFICATION_INCOME_RECEIPT = 'B. INCOME / RECEIPT';

    public const CLASSIFICATION_LOAN_MOVEMENT = 'C. ASSET / LOAN MOVEMENT';

    public const CLASSIFICATION_NON_CASH_DISTRIBUTION = 'D. NON-CASH DISTRIBUTION';

    public const CLASSIFICATION_FUNDING_TRANSFER = 'E. FUNDING / TRANSFER';

    public const CLASSIFICATION_HISTORICAL_DEPRECATED = 'F. HISTORICAL / DEPRECATED';

    public const CLASSIFICATION_OUT_OF_POCKET = 'G. OUT OF POCKET EXPENDITURE';

    /**
     * Map transaction type to classification
     */
    public static function classifyType(string $type, bool $isInternalTransfer = false): string
    {
        if ($isInternalTransfer || $type === 'transfer' || $type === 'out_of_pocket_reimbursement') {
            return self::CLASSIFICATION_FUNDING_TRANSFER;
        }

        return match ($type) {
            'intervention', 'education_fee_payment', 'withdrawal', 'debit', 'project_expense' => self::CLASSIFICATION_EXPENDITURE,
            'out_of_pocket_expenditure' => self::CLASSIFICATION_OUT_OF_POCKET,
            'deposit', 'credit', 'loan_repayment' => self::CLASSIFICATION_INCOME_RECEIPT,
            'loan_disbursement' => self::CLASSIFICATION_LOAN_MOVEMENT,
            'imprest_funding', 'imprest_replenishment' => self::CLASSIFICATION_FUNDING_TRANSFER,
            'imprest_expense' => self::CLASSIFICATION_HISTORICAL_DEPRECATED,
            'imprest_expense_void', 'imprest_replenishment_reversal', 'education_fee_payment_void' => self::CLASSIFICATION_HISTORICAL_DEPRECATED,
            default => self::CLASSIFICATION_EXPENDITURE,
        };
    }

    /**
     * Build base query for transactions
     */
    public function getTransactionsQuery(array $filters = [], string $mode = 'all'): Builder
    {
        $query = Transaction::query()
            ->with([
                'bankAccount',
                'destinationBankAccount',
                'transactionable',
            ]);

        // Date From Filter
        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        // Date To Filter
        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        // Bank Account Filter
        if (! empty($filters['bank_account_id'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('bank_account_id', $filters['bank_account_id'])
                    ->orWhere('destination_bank_account_id', $filters['bank_account_id']);
            });
        }

        // Transaction Type Filter
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Amount Min Filter
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }

        // Amount Max Filter
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $query->where('amount', '<=', (float) $filters['amount_max']);
        }

        // Reference / Description Search
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('reference', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        // Classification Filter
        if (! empty($filters['classification'])) {
            $this->applyClassificationFilter($query, $filters['classification']);
        }

        // Mode Filter (Expenditure Only vs All)
        if ($mode === 'expenditure_only') {
            $query->whereIn('type', [
                'intervention',
                'education_fee_payment',
                'withdrawal',
                'debit',
                'out_of_pocket_reimbursement',
            ]);
        }

        return $query->orderByDesc('date')->orderByDesc('created_at');
    }

    /**
     * Apply classification filter to query
     */
    protected function applyClassificationFilter(Builder $query, string $classification): void
    {
        switch ($classification) {
            case self::CLASSIFICATION_EXPENDITURE:
                $query->whereIn('type', ['intervention', 'education_fee_payment', 'withdrawal', 'debit']);
                break;
            case self::CLASSIFICATION_OUT_OF_POCKET:
                $query->whereIn('type', ['out_of_pocket_reimbursement']);
                break;
            case self::CLASSIFICATION_INCOME_RECEIPT:
                $query->whereIn('type', ['deposit', 'credit', 'loan_repayment']);
                break;
            case self::CLASSIFICATION_LOAN_MOVEMENT:
                $query->whereIn('type', ['loan_disbursement']);
                break;
            case self::CLASSIFICATION_FUNDING_TRANSFER:
                $query->whereIn('type', ['transfer', 'imprest_funding', 'imprest_replenishment', 'out_of_pocket_reimbursement']);
                break;
            case self::CLASSIFICATION_HISTORICAL_DEPRECATED:
                $query->whereIn('type', [
                    'imprest_expense',
                    'imprest_expense_void',
                    'imprest_replenishment_reversal',
                    'education_fee_payment_void',
                ]);
                break;
        }
    }

    /**
     * Compute Summary KPIs for filtered dataset
     */
    public function getKpis(array $filters = [], string $mode = 'all'): array
    {
        $query = $this->getTransactionsQuery($filters, $mode);

        $results = (clone $query)
            ->select('type', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->groupBy('type')
            ->get();

        $typeTotals = $results->pluck('total_amount', 'type')->toArray();
        $totalCount = $results->sum('total_count');

        $interventionExp = (float) ($typeTotals['intervention'] ?? 0);
        $educationExp = (float) ($typeTotals['education_fee_payment'] ?? 0);
        $withdrawalExp = (float) ($typeTotals['withdrawal'] ?? 0) + (float) ($typeTotals['debit'] ?? 0);

        // Out of Pocket Expenditures (from out_of_pocket_expenditures table where approved)
        $oopQuery = \App\Models\OutOfPocketExpenditure::query()->where('approval_status', 'approved');
        if (! empty($filters['date_from'])) {
            $oopQuery->whereDate('expenditure_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $oopQuery->whereDate('expenditure_date', '<=', $filters['date_to']);
        }
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $oopQuery->where('amount', '>=', (float) $filters['amount_min']);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $oopQuery->where('amount', '<=', (float) $filters['amount_max']);
        }
        $outOfPocketExp = (float) $oopQuery->sum('amount');

        // Project Expenses (from project_expenses table)
        $projectExpQuery = ProjectExpense::query();
        if (! empty($filters['date_from'])) {
            $projectExpQuery->whereDate('expense_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $projectExpQuery->whereDate('expense_date', '<=', $filters['date_to']);
        }
        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $projectExpQuery->where('amount', '>=', (float) $filters['amount_min']);
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $projectExpQuery->where('amount', '<=', (float) $filters['amount_max']);
        }
        $projectExp = (float) $projectExpQuery->sum('amount');

        $totalExpenditure = $interventionExp + $educationExp + $withdrawalExp + $projectExp + $outOfPocketExp;

        $incomeReceipts = (float) ($typeTotals['deposit'] ?? 0)
            + (float) ($typeTotals['credit'] ?? 0)
            + (float) ($typeTotals['loan_repayment'] ?? 0);

        $loanDisbursements = (float) ($typeTotals['loan_disbursement'] ?? 0);
        $loanRepayments = (float) ($typeTotals['loan_repayment'] ?? 0);
        $internalTransfers = (float) ($typeTotals['transfer'] ?? 0);

        $historicalImprestExp = (float) ($typeTotals['imprest_expense'] ?? 0);

        // Welfare collection count (non-cash)
        $welfareCount = DB::table('welfare_beneficiaries')->where('status', 'collected')->count();

        $netCashMovement = $incomeReceipts - ($totalExpenditure + $loanDisbursements);

        return [
            'total_expenditure' => $totalExpenditure,
            'income_receipts' => $incomeReceipts,
            'loan_disbursements' => $loanDisbursements,
            'loan_repayments' => $loanRepayments,
            'internal_transfers' => $internalTransfers,
            'project_expenditure' => $projectExp,
            'education_expenditure' => $educationExp,
            'intervention_expenditure' => $interventionExp,
            'out_of_pocket_expenditure' => $outOfPocketExp,
            'historical_imprest_expenditure' => $historicalImprestExp,
            'non_cash_welfare_count' => $welfareCount,
            'net_cash_movement' => $netCashMovement,
            'transaction_count' => $totalCount,
        ];
    }

    /**
     * Generate CSV export stream
     */
    public function exportCsv(array $filters = [], string $mode = 'all'): StreamedResponse
    {
        $filename = 'consolidated_financial_report_'.now()->format('Y_m_d_His').'.csv';

        $response = new StreamedResponse(function () use ($filters, $mode) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Date',
                'Reference',
                'Classification',
                'Type',
                'Description',
                'Bank Account',
                'Outflow (Debit)',
                'Inflow (Credit)',
                'Amount',
                'Source Module',
            ]);

            $query = $this->getTransactionsQuery($filters, $mode);

            $query->chunk(500, function ($transactions) use ($handle) {
                foreach ($transactions as $tx) {
                    $classification = self::classifyType($tx->type, $tx->isInternalTransfer());
                    $isCredit = $tx->isCreditType();

                    fputcsv($handle, [
                        $tx->date ? $tx->date->format('Y-m-d H:i') : '',
                        $tx->reference,
                        $classification,
                        $tx->type,
                        $tx->description,
                        $tx->bankAccount?->account_name ?? 'N/A',
                        $isCredit ? '0.00' : number_format((float) $tx->amount, 2, '.', ''),
                        $isCredit ? number_format((float) $tx->amount, 2, '.', '') : '0.00',
                        number_format((float) $tx->amount, 2, '.', ''),
                        $tx->transactionable_type ? class_basename($tx->transactionable_type) : 'Direct Ledger',
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
