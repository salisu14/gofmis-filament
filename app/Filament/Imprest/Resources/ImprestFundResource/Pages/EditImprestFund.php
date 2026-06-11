<?php

namespace App\Filament\Imprest\Resources\ImprestFundResource\Pages;

use App\Filament\Imprest\Resources\ImprestFundResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImprestFund extends EditRecord
{
    protected static string $resource = ImprestFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn(): bool => auth()->user()->hasRole('admin')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevent changing authorized amount without proper workflow
        unset($data['current_balance']);
        unset($data['bank_account_id']);
        return $data;
    }
}
