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
        $reason = $this->data['assignment_reason'] ?? null;

        // Run if coordinator changed or is explicitly set/cleared
        if ($zone->wasChanged('coordinator_id') || filled($zone->coordinator_id)) {
            app(ZoneCoordinatorService::class)
                ->assignCoordinator(
                    $zone,
                    $zone->coordinator_id,
                    auth()->id(),
                    $reason
                );

            Notification::make()
                ->success()
                ->title('Coordinator Assignment Updated')
                ->body('Coordinator assignment processed successfully and history logged.')
                ->send();

            $this->dispatch('refreshRelation');
        }
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->requiresConfirmation(fn () => $this->record->isDirty('coordinator_id'))
            ->modalHeading('Confirm Coordinator Assignment Change')
            ->modalDescription('Changing the coordinator will close the previous assignment history and log the new assignment.')
            ->modalSubmitActionLabel('Yes, continue');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
