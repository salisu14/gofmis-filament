<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('disableMfa')
                ->label('Disable MFA (Reset)')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Disable MFA for User')
                ->modalDescription('Are you sure you want to disable Multi-Factor Authentication for this user? They will be required to set it up again if their role mandates it.')
                ->visible(fn (\App\Models\User $record) => auth()->user()->isSuperAdmin() && ! $record->isSuperAdmin() && ! empty($record->app_authentication_secret))
                ->action(function (\App\Models\User $record) {
                    $record->update([
                        'app_authentication_secret' => null,
                        'mfa_confirmed_at' => null,
                        'app_authentication_recovery_codes' => null,
                    ]);
                    \App\Services\SecurityAuditService::log('MFA_ADMIN_DISABLED', "MFA disabled by admin for {$record->email}", auth()->user(), $record);
                    \Filament\Notifications\Notification::make()
                        ->title('MFA Disabled')
                        ->body("MFA has been successfully disabled for {$record->name}.")
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
