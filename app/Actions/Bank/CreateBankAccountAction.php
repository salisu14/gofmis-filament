<?php

namespace App\Actions\Bank;

use App\Data\Bank\CreateBankAccountData;
use App\Models\BankAccount;
use Illuminate\Support\Str;

class CreateBankAccountAction
{
    public function execute(CreateBankAccountData $data): BankAccount
    {
        return BankAccount::create([
            'account_name' => $data->name,
            'account_number' => $data->accountNumber ?? 'MANUAL-'.strtoupper(substr((string) Str::uuid(), 0, 12)),
            'opening_balance' => $data->initialBalance,
            'ledger_balance' => $data->initialBalance,
            'reserved_balance' => 0,
            'user_id' => $data->userId,
            'parent_bank_account_id' => $data->parentBankAccountId,
        ]);
    }
}
