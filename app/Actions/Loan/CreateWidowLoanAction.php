<?php

namespace App\Actions\Loan;

use App\Data\Loan\CreateWidowLoanData;
use App\Enums\WidowLoanStatus;
use App\Exceptions\InsufficientBankBalanceException;
use App\Models\BankAccount;
use App\Models\WidowLoan;
use Exception;

class CreateWidowLoanAction
{

    /**
     * @throws InsufficientBankBalanceException
     */
    public function execute(CreateWidowLoanData $data): WidowLoan
    {
        // 1. Retrieve the specific bank account (you need to add bank_account_id to your CreateLoanDTO)
        $bank = BankAccount::findOrFail($data->bankAccountId);

        if (!$bank) {
            throw new Exception('Bank account not configured.');
        }

        // 2. Check Balance
        if (!$bank->hasSufficientFunds($data->principalAmount)) {
            throw new InsufficientBankBalanceException('Insufficient funds in the selected bank account.');
        }

        // 2. Create the Loan
        return WidowLoan::create([
            'widow_id'        => $data->widowId,
            'bank_account_id' => $data->bankAccountId,
            'principal_amount' => $data->principalAmount,
            'duration_months' => $data->durationMonths,
            'purpose'         => $data->purpose,
            'status'          => WidowLoanStatus::DRAFT,
        ]);
    }
}
