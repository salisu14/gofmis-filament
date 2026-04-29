<?php
// app/Filament\Coordinator\Resources\WelfareRequestResource/Pages/ViewWelfareRequest.php

namespace App\Filament\Coordinator\Resources\WelfareRequestResource\Pages;

use App\Filament\Coordinator\Resources\WelfareRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWelfareRequest extends ViewRecord
{
    protected static string $resource = WelfareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn($record) => $record->status === \App\Enums\BeneficiaryStatus::PENDING),
        ];
    }
}
