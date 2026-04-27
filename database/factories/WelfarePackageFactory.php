<?php

namespace Database\Factories;

use App\Enums\WelfarePackageStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WelfarePackageFactory extends Factory
{
    protected $model = \App\Models\WelfarePackage::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+3 months');
        $endDate = (clone $startDate)->modify('+30 days');

        return [
            'id' => Str::uuid(),
            'name' => fake()->randomElement([
                    'Ramadan Food Support',
                    'Eid Clothing Assistance',
                    'Back to School Supplies',
                    'Winter Warmth Program',
                    'Medical Aid Package',
                ]) . ' ' . fake()->year(),
            'description' => fake()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => fake()->randomElement(WelfarePackageStatus::cases()),
            'created_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WelfarePackageStatus::DRAFT,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WelfarePackageStatus::OPEN,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WelfarePackageStatus::CLOSED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(30),
        ]);
    }
}
