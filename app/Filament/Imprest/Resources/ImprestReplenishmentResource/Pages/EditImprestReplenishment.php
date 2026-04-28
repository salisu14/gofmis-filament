<?php

namespace App\Filament\Imprest\Resources\ImprestReplenishmentResource\Pages;

use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use App\Models\ImprestReplenishment;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImprestReplenishment extends EditRecord
{
    protected static string $resource = ImprestReplenishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn(ImprestReplenishment $record): bool => $record->status === 'draft'
                ),
        ];
    }

    protected function canEdit(): bool
    {
        return $this->getRecord()->status === 'draft';
    }
}
