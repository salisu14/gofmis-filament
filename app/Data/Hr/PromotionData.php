<?php

namespace App\Data\Hr;

use Spatie\LaravelData\Data;

class PromotionData extends Data
{
    public function __construct(
        public string $employee_id,
        public float $new_salary,
        public ?string $new_rank,
        public string $effective_date,
        public ?string $reason
    ) {}

    public static function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'new_salary' => ['required', 'numeric', 'min:0'],
            'new_rank' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
