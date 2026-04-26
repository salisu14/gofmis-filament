<?php

namespace App\Filament\Imprest\Resources\ImprestTransactionResource\Pages;

use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Models\ImprestTransaction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImprestTransaction extends EditRecord
{
    protected static string $resource = ImprestTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (ImprestTransaction $record): bool =>
                    $record->status === 'pending' && auth()->user()->hasRole('admin')
                ),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Only allow editing certain fields if pending
        $record = $this->getRecord();

        if ($record->status !== 'pending') {
            // Remove protected fields
            unset($data['fund_id'], $data['quantity'], $data['unit_price'], $data['total_price']);
        }

        return $data;
    }

    protected function canEdit(): bool
    {
        $record = $this->getRecord();
        return $record->status === 'pending' && auth()->user()->can('update', $record);
    }
}
