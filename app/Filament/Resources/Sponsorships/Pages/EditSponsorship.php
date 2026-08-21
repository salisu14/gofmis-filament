<?php

namespace App\Filament\Resources\Sponsorships\Pages;

use App\Filament\Resources\Sponsorships\SponsorshipResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSponsorship extends EditRecord
{
    protected static string $resource = SponsorshipResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->end_date && $this->getRecord()->end_date->lt(now()->startOfDay())) {
            abort(403, 'Expired or historical sponsorships cannot be edited.');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
