<?php

namespace Database\Factories;

use App\Models\Orphan;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrphanFactory extends Factory
{
    protected $model = Orphan::class;

    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => \App\Enums\Gender::MALE,
            'date_of_birth' => now()->subYears(10),
            'widow_id' => \App\Models\Widow::factory(),
        ];
    }
}
