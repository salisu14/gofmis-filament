<?php

namespace Database\Seeders;

use App\Models\InterventionType;
use Illuminate\Database\Seeder;

class InterventionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Education - School Fees',
            'Education - Uniform & Books',
            'Education - Tuition Support',
            'Education - Examination Fees',
            'Education - Scholarship',
            'Healthcare Support',
            'Welfare Support',
        ];

        foreach ($types as $name) {
            InterventionType::firstOrCreate(['name' => $name]);
        }
    }
}
