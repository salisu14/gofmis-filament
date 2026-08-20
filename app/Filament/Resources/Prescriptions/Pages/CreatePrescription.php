<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Filament\Resources\Prescriptions\PrescriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrescription extends CreateRecord
{
    protected static string $resource = PrescriptionResource::class;

    public function mount(): void
    {
        parent::mount();

        $type = request()->query('prescribable_type');
        $id = request()->query('prescribable_id');

        if ($type && $id) {
            $this->form->fill([
                'prescribable_type' => $type,
                'prescribable_id' => $id,
                'prescription_date' => now()->toDateString(),
            ]);
        }
    }
}
