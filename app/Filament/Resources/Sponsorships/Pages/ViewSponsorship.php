<?php

namespace App\Filament\Resources\Sponsorships\Pages;

use App\Filament\Resources\Sponsorships\SponsorshipResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSponsorship extends ViewRecord
{
    protected static string $resource = SponsorshipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
