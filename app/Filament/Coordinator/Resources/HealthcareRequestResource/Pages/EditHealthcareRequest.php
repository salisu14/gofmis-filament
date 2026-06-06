<?php
// app/Filament/Coordinator/Resources/HealthcareRequestResource/Pages/EditHealthcareRequest.php

namespace App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages;

use App\Filament\Coordinator\Resources\HealthcareRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHealthcareRequest extends EditRecord
{
    protected static string $resource = HealthcareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasAnyRole(['admin', 'super_admin'])),
        ];
    }
}
