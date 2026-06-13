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
                $expectedBalance = (float) $row->cash_on_hand + (float) $row->receipts_total;
                $variance = $expectedBalance - (float) $row->authorized_amount;

                DB::table('imprest_reconciliations')
                    ->where('id', $row->id)
                    ->update([
                        'expected_balance' => $expectedBalance,
                        'actual_variance' => $variance,
                        'status' => abs($variance) < 0.01 ? 'completed' : 'flagged',
                    ]);
            });
    }
};
