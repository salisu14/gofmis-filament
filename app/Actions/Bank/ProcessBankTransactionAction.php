<?php

namespace App\Actions\Bank;

use App\Data\Bank\CreateBankTransactionData;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;

class ProcessBankTransactionAction
{
    /**
     * @throws \Throwable
     */
    public function execute(CreateBankTransactionData $data): BankAccount
    {
        return DB::transaction(function () use ($data) {
            $bank = BankAccount::findOrFail($data->bankAccountId);

            if ($data->type === 'DEBIT') {
                $bank->debit($data->amount);
            } elseif ($data->type === 'CREDIT') {
                $bank->credit($data->amount);
            } else {
                throw new \InvalidArgumentException('Invalid transaction type. Must be DEBIT or CREDIT.');
            }

            // Optional: Log this in a bank_transactions table if you have one
            // BankTransaction::create([
            //     'bank_account_id' => $bank->id,
            //     'amount' => $data->amount,
            //     'type' => $data->type,
            //     'reference' => $data->reference
            // ]);

            return $bank->fresh(); // Return fresh instance with updated balance
        });
    }
}
