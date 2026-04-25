<?php

namespace App\Actions\Loan;

use App\Models\WidowLoan;
use App\Services\WidowLoanService;

class DisburseWidowLoanAction
{
    public function execute(WidowLoan $loan): void
    {
        $service = new WidowLoanService();
        $service->disburseLoan($loan);
    }
}
