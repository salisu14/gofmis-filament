<?php

namespace App\Filament\Imprest\Resources\ImprestReplenishmentResource\Pages;

use App\Data\Imprest\CreateReplenishmentDto;
use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateImprestReplenishment extends CreateRecord
{
    protected static string $resource = ImprestReplenishmentResource::class;

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();

        $dto = new CreateReplenishmentDto(
            fundId: $data['fund_id'],
            periodStart: \Carbon\Carbon::parse($data['period_start']),
            periodEnd: \Carbon\Carbon::parse($data['period_end']),
            requestedBy: auth()->id(),
            notes: $data['notes'] ?? null,
        );

        $service = app(ImprestReplenishmentServiceInterface::class);

        try {
            $replenishment = $service->createRequest($dto);

            Notification::make()
                ->title('Replenishment Requested')
                ->success()
                ->body("Request submitted for approval.")
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $replenishment]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }
}
