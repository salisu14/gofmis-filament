<?php

namespace Database\Factories;

use App\Models\OrphanClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrphanClassFactory extends Factory
{
    protected $model = OrphanClass::class;

    public function definition()
    {
        return ['name' => $this->faker->word()];
    }
}
