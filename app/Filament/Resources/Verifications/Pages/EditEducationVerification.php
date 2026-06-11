<?php

namespace App\Filament\Resources\Verifications\Pages;

use App\Filament\Resources\Verifications\EducationVerificationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === 'approved' && ($data['verification_status'] ?? null) !== 'verified') {
            throw ValidationException::withMessages([
                'verification_status' => 'Education requests must be verified before approval.',
            ]);
        }

        if (($data['verification_status'] ?? null) === 'verified') {
            $data['verified_by'] = auth()->id();
            $data['verified_at'] = now();
            $data['reviewed_by'] ??= auth()->id();
            $data['reviewed_at'] ??= now();
        }

        if (($data['status'] ?? null) === 'approved') {
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        }

        if (($data['status'] ?? null) === 'rejected') {
            $data['reviewed_by'] = auth()->id();
            $data['reviewed_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
