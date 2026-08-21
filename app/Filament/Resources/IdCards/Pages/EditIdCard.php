<?php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIdCard extends EditRecord
{
    protected static string $resource = IdCardResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->status !== 'draft') {
            abort(403, 'Issued, active, revoked, or expired ID cards cannot be edited.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }
}
