<?php

namespace Database\Factories;

use App\Models\OrphanEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrphanEducationFactory extends Factory
{
    protected $model = OrphanEducation::class;

    public function definition()
    {
        return [
            'orphan_id' => \App\Models\Orphan::factory(),
            'institution_id' => \App\Models\Institution::factory(),
            'orphan_class_id' => \App\Models\OrphanClass::factory(),
        ];
    }
}
