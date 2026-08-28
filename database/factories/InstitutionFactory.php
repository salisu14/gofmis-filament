<?php

namespace Database\Factories;

use App\Enums\InstitutionType;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company(),
            'type' => InstitutionType::PRIMARY,
            'address' => $this->faker->address(),
        ];
    }
}
