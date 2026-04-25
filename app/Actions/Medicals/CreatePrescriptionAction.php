<?php

namespace App\Actions\Medicals;

use App\Data\Medicals\PrescriptionData;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;

class CreatePrescriptionAction
{
    /**
     * @throws \Throwable
     */
    public function execute(PrescriptionData $data): Prescription
    {
        return DB::transaction(function () use ($data) {
            // 1. Validate that the patient exists (simple check)
            if (!class_exists($data->prescribable_type)) {
                throw new \InvalidArgumentException("Invalid patient type.");
            }

            // 2. Create Prescription
            $prescription = Prescription::create([
                'prescribable_id' => $data->prescribable_id,
                'prescribable_type' => $data->prescribable_type,
                'illness' => $data->illness,
                'prescription_date' => $data->prescription_date,
                'lab_test_cost' => $data->lab_test_cost,
                'drug_cost' => $data->drug_cost,
                'doctor_name' => $data->doctor_name,
                'note' => $data->note,
                'user_id' => auth()->id(),
            ]);

            // 3. Attach Medications
            if (!empty($data->medication_ids)) {
                $prescription->medications()->attach($data->medication_ids);
            }

            return $prescription;
        });
    }
}
