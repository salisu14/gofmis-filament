<?php

namespace App\Filament\Imprest\Resources\ImprestReconciliationResource\Pages;

use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use App\Models\ImprestReconciliation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImprestReconciliation extends EditRecord
{
    protected static string $resource = ImprestReconciliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn(ImprestReconciliation $record): bool => $record->status === 'in_progress' && auth()->user()->hasRole('admin')
                ),
        ];
    }

    protected function canEdit(): bool
    {
        return $this->getRecord()->status === 'in_progress';
    }
}
