<?php

namespace App\Actions\Bank;

use App\Data\Bank\CreateBankTransactionData;
use App\Models\BankAccount;
use App\Models\Transaction;
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

            $type = match ($data->type) {
                'DEBIT' => 'withdrawal',
                'CREDIT' => 'deposit',
                default => null,
            };

            if (! $type) {
                throw new \InvalidArgumentException('Invalid transaction type. Must be DEBIT or CREDIT.');
            }

            Transaction::create([
                'bank_account_id' => $bank->id,
                'amount' => $data->amount,
                'type' => $type,
                'reference' => $data->reference,
                'description' => $data->description,
                'date' => now(),
                'is_system' => false,
            ]);

            return $bank->fresh(); // Return fresh instance with updated balance
        });
    }
}
