<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            \App\Filament\Actions\ResetUserPasswordAction::make(),
            \App\Filament\Actions\ResetMfaAction::make(),
            \App\Security\SensitiveActionConfirmation::apply(
                Actions\DeleteAction::make(),
                \App\Enums\SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
                'DELETE USER',
                'user_deletion'
            ),
        ];
    }
}
