<?php

namespace App\Filament\Actions;

use App\Enums\SensitiveConfirmationLevel;
use App\Models\User;
use App\Security\SensitiveActionConfirmation;
use App\Services\UserSecurityService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetPassword';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->visible(
                fn (User $record): bool => auth()->check()
                    && Gate::forUser(auth()->user())
                        ->allows('resetPassword', $record)
            )
            ->modalHeading('Reset User Password')
            ->modalDescription(
                'As an administrator, you may reset another user\'s password. '.
                'This action requires re-confirming your own password and typing the confirmation phrase.'
            )
            ->modalSubmitActionLabel('Reset Password');

        SensitiveActionConfirmation::apply(
            action: $this,
            level: SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            phrase: 'RESET USER PASSWORD',
            actionKey: 'reset_user_password',
            fields: [
                TextInput::make('new_password')
                    ->label('New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(
                        Password::default()
                            ->mixedCase()
                            ->numbers()
                            ->symbols()
                    )
                    ->autocomplete('new-password'),

                TextInput::make('new_password_confirmation')
                    ->label('Confirm New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('new_password')
                    ->autocomplete('new-password'),
            ],
        );

        $this->action(function (User $record, array $data): void {
            try {
                app(UserSecurityService::class)->resetPassword(
                    auth()->user(),
                    $record,
                    $data['new_password'],
                );

                Notification::make()
                    ->title('Password Reset Successfully')
                    ->body("Password for {$record->email} has been updated.")
                    ->success()
                    ->send();
            } catch (\Throwable $e) {
                report($e);

                Notification::make()
                    ->title('Password Reset Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
