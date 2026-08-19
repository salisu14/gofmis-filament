<?php

namespace App\Filament\Actions;

use App\Enums\SensitiveConfirmationLevel;
use App\Models\User;
use App\Security\SensitiveActionConfirmation;
use App\Services\MfaService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class ResetMfaAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'resetMfa';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Reset MFA')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(
                fn (User $record): bool => auth()->check()
                    && Gate::forUser(auth()->user())
                        ->allows('resetMfa', $record)
            )
            ->modalHeading('Reset User MFA')
            ->modalDescription(
                'As an administrator, you may reset another user\'s Multi-Factor Authentication. '.
                'This will invalidate their existing secret, recovery codes, and force enrollment if required. '.
                'This action requires re-confirming your own password and typing the confirmation phrase.'
            )
            ->modalSubmitActionLabel('Reset MFA');

        SensitiveActionConfirmation::apply(
            action: $this,
            level: SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            phrase: 'RESET MFA',
            actionKey: 'reset_user_mfa',
        );

        $this->action(function (User $record): void {
            try {
                $service = new MfaService;
                $service->resetMfa(auth()->user(), $record);

                Notification::make()
                    ->title('MFA Reset Successfully')
                    ->body("Multi-Factor Authentication for {$record->email} has been reset.")
                    ->success()
                    ->send();
            } catch (\Throwable $e) {
                report($e);

                Notification::make()
                    ->title('MFA Reset Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
