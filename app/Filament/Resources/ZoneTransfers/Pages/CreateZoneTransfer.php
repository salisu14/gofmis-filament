<?php
// app/Filament/Resources/ZoneTransfers/Pages/CreateZoneTransfer.php

namespace App\Filament\Resources\ZoneTransfers\Pages;

use App\Filament\Resources\ZoneTransfers\ZoneTransferResource;
use App\Models\Deceased;
use App\Services\Deceased\ZoneTransferService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateZoneTransfer extends CreateRecord
{
    protected static string $resource = ZoneTransferResource::class;

    /**
     * Handle the record creation using the service.
     *
     * @param array $data Form data from Filament
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Fetch the deceased record
        $deceased = Deceased::findOrFail($data['deceased_id']);

        $service = app(ZoneTransferService::class);

        try {
            $transfer = $service->transfer(
                deceased: $deceased,
                toZoneId: $data['to_zone_id'],
                reason: $data['reason'] ?? null,
                performedBy: $data['moved_by'] ?? auth()->id(),
            );

            // Success notification
            Notification::make()
                ->title('Zone Transfer Completed')
                ->body("Family moved from {$transfer->fromZone->name} to {$transfer->toZone->name}")
                ->success()
                ->send();

            return $transfer;

        } catch (\InvalidArgumentException $e) {
            // Same zone error
            Notification::make()
                ->title('Transfer Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            // Halt creation to prevent redirect
            $this->halt();
        }
    }

    /**
     * Redirect after successful creation.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
