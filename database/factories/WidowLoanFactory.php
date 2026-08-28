<?php

namespace Database\Factories;

use App\Models\WidowLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

class WidowLoanFactory extends Factory
{
    protected $model = WidowLoan::class;

    public function definition()
    {
        return [
            'widow_id' => \App\Models\Widow::factory(),
            'principal_amount' => 100000,
            'duration_months' => 12,
            'repayment_frequency' => \App\Enums\LoanRepaymentFrequency::MONTHLY,
            'status' => \App\Enums\WidowLoanStatus::DISBURSED,
            'disbursed_at' => now()->subMonths(1),
            'fully_repaid' => false,
        ];
    }
}
