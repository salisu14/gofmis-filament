<?php

namespace Database\Seeders;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MedicationsTableSeeder extends Seeder
{
    public function run()
    {
        $now = now();
        $userId = User::first()->id;

        $medications = [
            ['name' => 'Aspirin', 'description' => 'Used as a pain reliever, anti-inflammatory, and to reduce fever.'],
            ['name' => 'Ibuprofen', 'description' => 'An over-the-counter nonsteroidal anti-inflammatory drug (NSAID) used to relieve pain, reduce inflammation, and lower fever.'],
            ['name' => 'Acetaminophen (Paracetamol)', 'description' => 'A common pain reliever and fever reducer.'],
            ['name' => 'Amoxicillin', 'description' => 'An antibiotic used to treat bacterial infections.'],
            ['name' => 'Lisinopril', 'description' => 'An angiotensin-converting enzyme (ACE) inhibitor used to treat high blood pressure and heart failure.'],
            ['name' => 'Lipitor (Atorvastatin)', 'description' => 'A statin drug used to lower cholesterol levels.'],
            ['name' => 'Metformin', 'description' => 'An oral antidiabetic medication used to manage type 2 diabetes.'],
            ['name' => 'Albuterol', 'description' => 'A bronchodilator used to relieve bronchospasms in conditions like asthma and chronic obstructive pulmonary disease (COPD).'],
            ['name' => 'Omeprazole', 'description' => 'A proton pump inhibitor (PPI) used to reduce stomach acid production, often prescribed for acid reflux and peptic ulcers.'],
            ['name' => 'Levothyroxine', 'description' => 'A synthetic thyroid hormone used to treat hypothyroidism.'],
            ['name' => 'Prednisone', 'description' => 'A corticosteroid used to reduce inflammation and suppress the immune system in various conditions.'],
            ['name' => 'Warfarin', 'description' => 'An anticoagulant (blood thinner) used to prevent blood clots and strokes.'],
            ['name' => 'Hydrochlorothiazide', 'description' => 'A diuretic often used to treat high blood pressure and edema.'],
            ['name' => 'Prozac (Fluoxetine)', 'description' => 'An antidepressant in the selective serotonin reuptake inhibitor (SSRI) class.'],
            ['name' => 'Vitamin D', 'description' => 'A supplement used to maintain healthy bones and aid in the absorption of calcium.'],
        ];

        $data = array_map(fn ($med) => [
            'id'          => Str::uuid(),
            'name'        => $med['name'],
            'description' => $med['description'],
            'user_id'     => $userId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $medications);

        Medication::insert($data);
    }
}
