<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('imprest_reconciliations')
            ->join('imprest_funds', 'imprest_reconciliations.fund_id', '=', 'imprest_funds.id')
            ->whereNull('imprest_reconciliations.deleted_at')
            ->select([
                'imprest_reconciliations.id',
                'imprest_reconciliations.fund_id',
                'imprest_reconciliations.reconciliation_date',
                'imprest_reconciliations.cash_on_hand',
                'imprest_reconciliations.receipts_total',
                'imprest_funds.authorized_amount',
            ])
            ->orderBy('imprest_reconciliations.id')
            ->get()
            ->each(function (object $row): void {
                $reconciliationDate = \Carbon\Carbon::parse($row->reconciliation_date);
                $start = $reconciliationDate->copy()->startOfMonth()->toDateString();
                $end = $reconciliationDate->toDateString();

                $processedReplenishments = (float) DB::table('imprest_replenishments')
                    ->where('fund_id', $row->fund_id)
                    ->where('status', 'processed')
                    ->whereDate('period_start', '<=', $end)
                    ->whereDate('period_end', '>=', $start)
                    ->whereDate('processed_at', '<=', $end)
                    ->sum('amount');

                $authorizedAmount = (float) $row->authorized_amount;
                $variance = ((float) $row->cash_on_hand + (float) $row->receipts_total - $processedReplenishments) - $authorizedAmount;

                DB::table('imprest_reconciliations')
                    ->where('id', $row->id)
                    ->update([
                        'expected_balance' => $authorizedAmount,
                        'actual_variance' => $variance,
                        'status' => abs($variance) < 0.01 ? 'completed' : 'flagged',
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('imprest_reconciliations')
            ->join('imprest_funds', 'imprest_reconciliations.fund_id', '=', 'imprest_funds.id')
            ->whereNull('imprest_reconciliations.deleted_at')
            ->select([
                'imprest_reconciliations.id',
                'imprest_reconciliations.cash_on_hand',
                'imprest_reconciliations.receipts_total',
                'imprest_funds.authorized_amount',
            ])
            ->orderBy('imprest_reconciliations.id')
            ->get()
            ->each(function (object $row): void {
                $authorizedAmount = (float) $row->authorized_amount;
                $variance = ((float) $row->cash_on_hand + (float) $row->receipts_total) - $authorizedAmount;

                DB::table('imprest_reconciliations')
                    ->where('id', $row->id)
                    ->update([
                        'expected_balance' => $authorizedAmount,
                        'actual_variance' => $variance,
                        'status' => abs($variance) < 0.01 ? 'completed' : 'flagged',
                    ]);
            });
    }
};
