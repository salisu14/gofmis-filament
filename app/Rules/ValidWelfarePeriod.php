<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidWelfarePeriod implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $startDate = request('start_date');
        $endDate = request('end_date');

        if ($startDate && $endDate && $endDate <= $startDate) {
            $fail('The end date must be after the start date.');
        }

        if ($startDate && now()->parse($startDate)->isBefore(now()->subDay()) && request()->isMethod('post')) {
            $fail('The start date cannot be in the past for new packages.');
        }
    }
}
