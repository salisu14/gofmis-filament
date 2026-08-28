<?php

namespace Database\Factories;

use App\Models\Widow;
use Illuminate\Database\Eloquent\Factories\Factory;

class WidowFactory extends Factory
{
    protected $model = Widow::class;

    public function definition()
    {
        return [
            'first_name' => $this->faker->firstNameFemale(),
            'last_name' => $this->faker->lastName(),
            'deceased_id' => \App\Models\Deceased::factory(),
        ];
    }
}
