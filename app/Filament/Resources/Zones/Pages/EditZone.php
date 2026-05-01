<?php

namespace App\Filament\Resources\Zones\Pages;

use App\Filament\Resources\Zones\ZoneResource;
use App\Services\ZoneCoordinatorService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditZone extends EditRecord
{
    protected static string $resource = ZoneResource::class;

    /**
     * Run coordinator logic AFTER save
     */
    protected function afterSave(): void
    {
        $zone = $this->record;

        // Only run if coordinator actually changed and new one is set
        if ($zone->wasChanged('coordinator_id') && $zone->coordinator_id) {
            app(ZoneCoordinatorService::class)
                ->assignCoordinator(
                    $zone,
                    $zone->coordinator_id,
                    auth()->id()
                );

            Notification::make()
                ->success()
                ->title('Coordinator Updated')
                ->body('Coordinator assigned successfully and history logged.')
                ->send();
        }
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->requiresConfirmation(fn () => $this->record->isDirty('coordinator_id'))
            ->modalHeading('Confirm Coordinator Change')
            ->modalDescription('Changing the coordinator will replace the current one and update history.')
            ->modalSubmitActionLabel('Yes, continue');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
