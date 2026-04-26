<?php

namespace Database\Factories;

use App\Enums\FundStatus;
use App\Models\ImprestFund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ImprestFundFactory extends Factory
{
    protected $model = ImprestFund::class;

    public function definition(): array
    {
        $authorized = fake()->randomFloat(2, 500, 5000);

        return [
            'id' => (string) Str::uuid(),
            'custodian_id' => User::factory(),
            'location' => fake()->city(),
            'authorized_amount' => $authorized,
            'current_balance' => $authorized,
            'last_reconciled_at' => fake()->optional()->dateTimeBetween('-90 days', '-1 day'),
            'status' => FundStatus::ACTIVE->value,
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
