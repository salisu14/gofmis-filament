<?php

namespace App\Security;

use App\Enums\SensitiveConfirmationLevel;
use App\Services\SecurityAuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class SensitiveActionConfirmation
{
    public static function apply(
        Action $action,
        SensitiveConfirmationLevel $level,
        string $phrase = '',
        string $actionKey = 'sensitive_action',
        array $fields = [],
    ): Action {
        if ($level === SensitiveConfirmationLevel::NONE) {
            return $action->schema($fields);
        }

        $securityFields = [];

        if (in_array(
            $level,
            [
                SensitiveConfirmationLevel::PASSWORD,
                SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            ],
            true
        )) {
            $securityFields[] = TextInput::make('confirm_password')
                ->label('Current Password')
                ->password()
                ->required()
                ->revealable()
                ->autocomplete('current-password')
                ->dehydrated(false)
                ->rules([
                    function () use ($actionKey) {
                        return function (
                            string $attribute,
                            mixed $value,
                            \Closure $fail
                        ) use ($actionKey): void {
                            $user = Auth::user();

                            if (! $user) {
                                $fail('Unauthenticated.');

                                return;
                            }

                            $limiterKey = sprintf(
                                'sensitive-action-password:%s:%s',
                                $user->getKey(),
                                $actionKey
                            );

                            if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
                                $seconds = RateLimiter::availableIn($limiterKey);

                                $fail(
                                    "Too many failed password attempts. Please try again in {$seconds} seconds."
                                );

                                return;
                            }

                            if (! Hash::check((string) $value, $user->password)) {
                                RateLimiter::hit($limiterKey, 60);

                                SecurityAuditService::log(
                                    'SENSITIVE_ACTION_FAILED',
                                    "Failed password confirmation for {$actionKey}",
                                    $user,
                                    null,
                                    [
                                        'reason' => 'Incorrect password',
                                        'action_key' => $actionKey,
                                    ]
                                );

                                $fail('Incorrect password.');

                                return;
                            }

                            RateLimiter::clear($limiterKey);
                        };
                    },
                ]);
        }

        if (in_array(
            $level,
            [
                SensitiveConfirmationLevel::TYPED_PHRASE,
                SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            ],
            true
        )) {
            if ($phrase === '') {
                throw new \InvalidArgumentException(
                    'A confirmation phrase is required for typed confirmation.'
                );
            }

            $securityFields[] = TextInput::make('confirm_phrase')
                ->label("To confirm, type: {$phrase}")
                ->required()
                ->autocomplete(false)
                ->dehydrated(false)
                ->placeholder($phrase)
                ->rules([
                    function () use ($phrase, $actionKey) {
                        return function (
                            string $attribute,
                            mixed $value,
                            \Closure $fail
                        ) use ($phrase, $actionKey): void {
                            if ((string) $value === $phrase) {
                                return;
                            }

                            $user = Auth::user();

                            SecurityAuditService::log(
                                'SENSITIVE_ACTION_FAILED',
                                "Failed phrase confirmation for {$actionKey}",
                                $user,
                                null,
                                [
                                    'reason' => 'Incorrect confirmation phrase',
                                    'action_key' => $actionKey,
                                ]
                            );

                            $fail('The typed confirmation phrase does not match.');
                        };
                    },
                ]);
        }

        $action->schema([
            ...$fields,
            ...$securityFields,
        ]);

        $action->before(function () use ($actionKey): void {
            $user = Auth::user();

            SecurityAuditService::log(
                'SENSITIVE_ACTION_CONFIRMED',
                "Successfully confirmed sensitive action: {$actionKey}",
                $user,
                null,
                [
                    'action_key' => $actionKey,
                ]
            );
        });

        return $action;
    }
}
