<?php

namespace App\Actions\Loan;

use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Models\WidowLoanRepayment;
use App\Services\WidowLoanService;

class RecordWidowLoanRepaymentAction
{
    public function execute(RecordWidowLoanRepaymentData $data): WidowLoanRepayment
    {
        $service = new WidowLoanService();
        return $service->recordRepayment($data);
    }
}
