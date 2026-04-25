<?php

namespace App\Actions\Loan;

use App\Data\Loan\CreateWidowLoanData;
use App\Enums\LoanStatus;
use App\Exceptions\InsufficientBankBalanceException;
use App\Exceptions\InsufficientFundsException;
use App\Models\BankAccount;
use App\Models\Loan;
use Exception;

class CreateWidowLoanAction
{

    /**
     * @throws InsufficientFundsException
     * @throws InsufficientBankBalanceException
     */
    public function execute(CreateWidowLoanData $data): Loan
    {
        // 1. Retrieve the specific bank account (you need to add bank_account_id to your CreateLoanDTO)
        $bank = BankAccount::findOrFail($data->bankAccountId);

        if (!$bank) {
            throw new Exception('Bank account not configured.');
        }

        // 2. Check Balance
        if (!$bank->hasSufficientFunds($data->amount)) {
            throw new InsufficientBankBalanceException('Insufficient funds in the selected bank account.');
        }

        // Using strict float comparison
        if ($data->amount > $bank->balance) {
            throw new InsufficientFundsException('Insufficient funds to grant this loan.');
        }

        // 2. Create the Loan
        return Loan::create([
            'widow_id'        => $data->widowId,
            'amount'          => $data->amount,
            'original_amount' => $data->amount, // Snapshot original amount
            'business'        => $data->business,
            'description'     => $data->description,
            'status'          => LoanStatus::PENDING,
        ]);
    }
}
