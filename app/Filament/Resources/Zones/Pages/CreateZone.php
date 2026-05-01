<?php

namespace App\Filament\Resources\Zones\Pages;

use App\Filament\Resources\Zones\ZoneResource;
use App\Services\ZoneCoordinatorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateZone extends CreateRecord
{
    protected static string $resource = ZoneResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->coordinator_id) {
            app(ZoneCoordinatorService::class)->assignCoordinator(
                $this->record,
                $this->record->coordinator_id,
                auth()->id(),
            );

            // Only send success notification — validation errors are handled by form rules now
            Notification::make()
                ->title('Zone created and coordinator assigned')
                ->success()
                ->send();
        }
    }
}
