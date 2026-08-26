<?php

namespace App\Filament\Resources\WelfarePackages\Pages;

use App\Filament\Resources\WelfarePackages\WelfarePackageResource;
use App\Models\WelfarePackage;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWelfarePackage extends EditRecord
{
    protected static string $resource = WelfarePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->isDraft() && ! $this->record->hasNominations()),
        ];
    }

    /**
     * Block navigation to the edit page for non-editable packages.
     * Covers direct URL access attempts (forged navigation).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var WelfarePackage $record */
        $record = $this->record;

        if (! $record->isCompositionEditable()) {
            Notification::make()
                ->title('This package cannot be edited.')
                ->body('Package composition is locked once nominations have been made, or when the package is Closed.')
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }

        return $data;
    }
}
