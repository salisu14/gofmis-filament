<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Filament\Resources\Prescriptions\PrescriptionResource;
use App\Models\Prescription;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPrescription extends ViewRecord
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Prescription $record) => $record->isPending()),
            \App\Filament\Actions\MarkHealthcareTreatedAction::make(),
        ];
    }
}
