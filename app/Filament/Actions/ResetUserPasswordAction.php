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

        SensitiveActionConfirmation::apply(
            $this,
            SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            'RESET USER PASSWORD',
            'reset_user_password'
        );

        $this->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->visible(fn (User $record) => auth()->check() && Gate::forUser(auth()->user())->allows('resetPassword', $record))
            ->modalHeading('Reset User Password')
            ->modalDescription('As an administrator, you may reset another user\'s password. This action requires re-confirming your OWN password and typing the confirmation phrase.')
            ->modalSubmitActionLabel('Reset Password')
            ->form([
                TextInput::make('new_password')
                    ->label('New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default()->mixedCase()->numbers()->symbols())
                    ->autocomplete('new-password'),

                TextInput::make('new_password_confirmation')
                    ->label('Confirm New Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('new_password')
                    ->autocomplete('new-password'),
            ])
            ->action(function (User $record, array $data): void {
                $service = new UserSecurityService;

                try {
                    $service->resetPassword(auth()->user(), $record, $data['new_password']);

                    Notification::make()
                        ->title('Password Reset Successfully')
                        ->body("Password for {$record->email} has been updated.")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Password Reset Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
