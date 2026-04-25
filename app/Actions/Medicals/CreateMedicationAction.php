<?php

namespace App\Actions\Medicals;

use App\Data\Medicals\MedicationData;
use App\Models\Medication;

class CreateMedicationAction
{
    public function execute(MedicationData $data): Medication
    {
        return Medication::create([
            'name' => $data->name,
            'description' => $data->description,
            'user_id' => auth()->id(),
        ]);
    }
}
