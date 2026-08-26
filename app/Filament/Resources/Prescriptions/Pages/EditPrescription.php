<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Filament\Resources\Prescriptions\PrescriptionResource;
use App\Models\Prescription;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrescription extends EditRecord
{
    protected static string $resource = PrescriptionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->isTreated()) {
            $this->redirect(PrescriptionResource::getUrl('view', ['record' => $this->getRecord()]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Actions\MarkHealthcareTreatedAction::make(),
            DeleteAction::make()
                ->visible(fn (Prescription $record) => $record->isPending()),
        ];
    }
}
