<?php

namespace App\Filament\Resources\Verifications\Pages;

use App\Filament\Resources\Verifications\EducationVerificationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEducationVerification extends EditRecord
{
    protected static string $resource = EducationVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasRole(['admin', 'super_admin'])),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Verification updated successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
