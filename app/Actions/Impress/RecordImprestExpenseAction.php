<?php

namespace App\Actions\Impress;

use App\Models\ImprestTransaction;
use Illuminate\Support\Facades\DB;

class RecordImprestExpenseAction
{
    /**
     * @throws \Throwable
     */
    public function execute(array $data): ImprestTransaction
    {
        return DB::transaction(function () use ($data) {

            $data['total_amount'] = $data['quantity'] * $data['unit_price'];

            $transaction = ImprestTransaction::create($data);

            // Reduce balance
            $transaction->imprest->decrement('current_balance', $transaction->total_amount);

            return $transaction;
        });
    }
}
