<?php

namespace App\Services;

use App\Models\BankAccount;

class BankAccountService
{
    /**
     * Get total balance across all bank accounts.
     */
    public function getTotalSystemBalance(): float
    {
        return (float) BankAccount::sum('ledger_balance');
    }

    /**
     * Get bank account details with formatted balance.
     */
    public function getAccountWithStats(string $bankId): ?BankAccount
    {
        return BankAccount::find($bankId);
    }

    /**
     * Find a default bank account.
     * Useful if your system operates primarily out of one main account.
     */
    public function getDefaultBank(): ?BankAccount
    {
        // Logic to determine default, e.g., the first one created or marked as default
        return BankAccount::first();
    }
}
