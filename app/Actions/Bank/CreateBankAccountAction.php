<?php

namespace App\Actions\Bank;

use App\Data\Bank\CreateBankAccountData;
use App\Models\BankAccount;

class CreateBankAccountAction
{
    public function execute(CreateBankAccountData $data): BankAccount
    {
        return BankAccount::create([
            'name' => $data->name,
            'amount' => $data->initialBalance,
            'balance' => $data->initialBalance, // Set initial balance same as amount
            'user_id' => $data->userId
        ]);
    }
}
