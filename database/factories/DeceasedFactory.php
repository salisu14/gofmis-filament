<?php

namespace Database\Factories;

use App\Enums\VulnerabilityStatus;
use App\Models\Deceased;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeceasedFactory extends Factory
{
    protected $model = Deceased::class;

    public function definition(): array
    {
        $deathDate = fake()->dateTimeBetween('-5 years', '-2 months');
        $regDate = fake()->dateTimeBetween($deathDate, 'now');

        return [
            'id' => Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'nin' => fake()->unique()->numerify('###########'),
            'reg_no' => 'DEC-'.fake()->unique()->numberBetween(10000, 99999),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->phoneNumber(),
            'vulnerability_status' => fake()->randomElement(VulnerabilityStatus::cases()),
            'date_of_death' => $deathDate->format('Y-m-d'),
            'date_registered' => $regDate->format('Y-m-d'),
            'zone_id' => Zone::first()?->id,
        ];
    }
}
