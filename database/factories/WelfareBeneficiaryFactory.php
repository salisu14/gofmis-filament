<?php

namespace Database\Factories;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfarePackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WelfareBeneficiaryFactory extends Factory
{
    protected $model = \App\Models\WelfareBeneficiary::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'welfare_package_id' => WelfarePackage::factory(),
            'deceased_id' => Deceased::factory(),
            'suggested_by' => User::factory(),
            'approved_by' => null,
            'status' => BeneficiaryStatus::PENDING,
            'rejection_reason' => null,
            'collection_status' => CollectionStatus::NOT_COLLECTED,
            'collected_at' => null,
            'collected_by' => null,
            'collection_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BeneficiaryStatus::APPROVED,
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BeneficiaryStatus::REJECTED,
            'approved_by' => User::factory(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function collected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BeneficiaryStatus::APPROVED,
            'approved_by' => User::factory(),
            'collection_status' => CollectionStatus::COLLECTED,
            'collected_at' => now(),
            'collected_by' => User::factory(),
        ]);
    }
}
