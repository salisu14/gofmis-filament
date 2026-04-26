<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\Pages;

use App\Filament\Imprest\Resources\ImprestFundResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImprestFund extends CreateRecord
{
    protected static string $resource = ImprestFundResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Fund created successfully';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['current_balance'] = $data['authorized_amount'];
        return $data;
    }
}
