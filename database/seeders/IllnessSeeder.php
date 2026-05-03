<?php
// database/seeders/IllnessSeeder.php

namespace Database\Seeders;

use App\Enums\IllnessCategory;
use App\Models\Illness;
use Illuminate\Database\Seeder;

class IllnessSeeder extends Seeder
{
    public function run(): void
    {
        $illnesses = [
            ['name' => 'Malaria', 'category' => IllnessCategory::Infectious],
            ['name' => 'Typhoid Fever', 'category' => IllnessCategory::Infectious],
            ['name' => 'Upper Respiratory Tract Infection', 'category' => IllnessCategory::Respiratory],
            ['name' => 'Pneumonia', 'category' => IllnessCategory::Respiratory],
            ['name' => 'Hypertension', 'category' => IllnessCategory::Chronic],
            ['name' => 'Diabetes Mellitus', 'category' => IllnessCategory::Chronic],
            ['name' => 'Peptic Ulcer Disease', 'category' => IllnessCategory::Gastrointestinal],
            ['name' => 'Urinary Tract Infection', 'category' => IllnessCategory::Infectious],
            ['name' => 'Skin Infection', 'category' => IllnessCategory::Dermatological],
            ['name' => 'Anemia', 'category' => IllnessCategory::Hematological],
            ['name' => 'Gastroenteritis', 'category' => IllnessCategory::Gastrointestinal],
            ['name' => 'Arthritis', 'category' => IllnessCategory::Musculoskeletal],
            ['name' => 'Asthma', 'category' => IllnessCategory::Respiratory],
            ['name' => 'Allergic Rhinitis', 'category' => IllnessCategory::Allergic],
            ['name' => 'Conjunctivitis', 'category' => IllnessCategory::Ophthalmological],
            ['name' => 'Otitis Media', 'category' => IllnessCategory::Ent],
            ['name' => 'Dental Caries', 'category' => IllnessCategory::Dental],
            ['name' => 'Malnutrition', 'category' => IllnessCategory::Nutritional],
            ['name' => 'Dysentery', 'category' => IllnessCategory::Gastrointestinal],
            ['name' => 'Helminthiasis', 'category' => IllnessCategory::Parasitic],
            ['name' => 'Depression', 'category' => IllnessCategory::Psychiatric],
            ['name' => 'Epilepsy', 'category' => IllnessCategory::Neurological],
            ['name' => 'Heart Failure', 'category' => IllnessCategory::Cardiovascular],
            ['name' => 'Goiter', 'category' => IllnessCategory::Endocrine],
            ['name' => 'Fracture', 'category' => IllnessCategory::Trauma],
            ['name' => 'Burns', 'category' => IllnessCategory::Trauma],
        ];

        foreach ($illnesses as $illness) {
            Illness::firstOrCreate(
                ['name' => $illness['name']],
                [
                    'category' => $illness['category'],
                    'description' => null,
                ]
            );
        }
    }
}
