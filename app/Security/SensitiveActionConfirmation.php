<?php

namespace App\Security;

use App\Enums\SensitiveConfirmationLevel;
use App\Services\SecurityAuditService;
use Filament\Actions\MountableAction;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class SensitiveActionConfirmation
{
    public static function apply(
        MountableAction $action,
        SensitiveConfirmationLevel $level,
        string $phrase,
        string $actionKey = 'sensitive_action'
    ): MountableAction {
        if ($level === SensitiveConfirmationLevel::NONE) {
            return $action;
        }

        // Get the existing form schema or configuration closure
        $existingForm = $action->getForm();

        // Define a new form schema that prepends/appends the security confirmation inputs
        $action->form(function (MountableAction $action) use ($existingForm, $level, $phrase, $actionKey) {
            // Resolve existing fields
            $fields = [];
            if ($existingForm) {
                if (is_array($existingForm)) {
                    $fields = $existingForm;
                } elseif (is_callable($existingForm)) {
                    $fields = app()->call($existingForm, ['action' => $action]);
                }
            }

            $securityFields = [];

            // Add Password confirm field
            if (in_array($level, [SensitiveConfirmationLevel::PASSWORD, SensitiveConfirmationLevel::PASSWORD_AND_PHRASE])) {
                $securityFields[] = TextInput::make('confirm_password')
                    ->label('Current Password')
                    ->password()
                    ->required()
                    ->revealable()
                    ->dehydrated(false)
                    ->rules([
                        function () use ($actionKey) {
                            return function ($attribute, $value, $fail) use ($actionKey) {
                                $user = Auth::user();
                                if (! $user) {
                                    $fail('Unauthenticated.');

                                    return;
                                }

                                $limiterKey = "sensitive-action-password:{$user->id}:{$actionKey}";
                                if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
                                    $seconds = RateLimiter::availableIn($limiterKey);
                                    $fail("Too many failed password attempts. Please try again in {$seconds} seconds.");

                                    return;
                                }

                                if (! Hash::check($value, $user->password)) {
                                    RateLimiter::hit($limiterKey, 60);
                                    SecurityAuditService::log(
                                        'SENSITIVE_ACTION_FAILED',
                                        "Failed password confirmation for {$actionKey}",
                                        $user,
                                        null,
                                        ['reason' => 'Incorrect password', 'action_key' => $actionKey]
                                    );
                                    $fail('Incorrect password.');
                                } else {
                                    RateLimiter::clear($limiterKey);
                                }
                            };
                        },
                    ]);
            }

            // Add Phrase confirm field
            if (in_array($level, [SensitiveConfirmationLevel::TYPED_PHRASE, SensitiveConfirmationLevel::PASSWORD_AND_PHRASE])) {
                $securityFields[] = TextInput::make('confirm_phrase')
                    ->label("To confirm, type: {$phrase}")
                    ->required()
                    ->dehydrated(false)
                    ->placeholder($phrase)
                    ->rules([
                        function () use ($phrase, $actionKey) {
                            return function ($attribute, $value, $fail) use ($phrase, $actionKey) {
                                if ($value !== $phrase) {
                                    $user = Auth::user();
                                    SecurityAuditService::log(
                                        'SENSITIVE_ACTION_FAILED',
                                        "Failed phrase confirmation for {$actionKey}",
                                        $user,
                                        null,
                                        ['reason' => 'Incorrect confirmation phrase', 'action_key' => $actionKey]
                                    );
                                    $fail('The typed confirmation phrase does not match.');
                                }
                            };
                        },
                    ]);
            }

            return array_merge($securityFields, $fields);
        });

        // Add a standard hook to log the success of the action
        $action->before(function () use ($actionKey) {
            $user = Auth::user();
            SecurityAuditService::log(
                'SENSITIVE_ACTION_CONFIRMED',
                "Successfully completed sensitive action: {$actionKey}",
                $user,
                null,
                ['action_key' => $actionKey]
            );
        });

        return $action;
    }
}
