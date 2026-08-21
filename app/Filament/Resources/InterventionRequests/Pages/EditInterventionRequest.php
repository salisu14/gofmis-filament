<?php

namespace App\Filament\Resources\InterventionRequests\Pages;

use App\Filament\Resources\InterventionRequests\InterventionRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInterventionRequest extends EditRecord
{
    protected static string $resource = InterventionRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! in_array($this->getRecord()->status, ['pending', 'under_review'], true)) {
            abort(403, 'Completed, fulfilled or rejected intervention requests cannot be edited.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => in_array($record->status, ['pending', 'under_review'], true)),
        ];
    }
}
