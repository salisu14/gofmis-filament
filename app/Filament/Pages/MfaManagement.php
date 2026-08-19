<?php

namespace App\Filament\Pages;

use App\Enums\SensitiveConfirmationLevel;
use App\Enums\UserStatus;
use App\Models\User;
use App\Security\SensitiveActionConfirmation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class MfaManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected string $view = 'filament.pages.mfa-management';

    protected static ?string $title = 'MFA Management';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && (new \App\Policies\UserPolicy)->viewAny($user);
    }

    public function getStats(): array
    {
        $mandatoryRoles = config('security.mfa.mandatory_roles', ['super_admin', 'admin', 'custodian', 'auditor']);
        $existingRoles = \App\Models\Role::whereIn('name', $mandatoryRoles)->pluck('name')->toArray();

        $totalActive = User::where('is_active', true)->where('status', UserStatus::ACTIVE)->count();
        $mfaEnabled = User::whereNotNull('app_authentication_secret')->whereNotNull('mfa_confirmed_at')->count();

        $mfaRequired = 0;
        $requiredNotEnabled = 0;

        if (! empty($existingRoles)) {
            $mfaRequired = User::role($existingRoles)
                ->where('is_active', true)
                ->where('status', UserStatus::ACTIVE)
                ->count();

            $requiredNotEnabled = User::role($existingRoles)
                ->where('is_active', true)
                ->where('status', UserStatus::ACTIVE)
                ->get()
                ->filter(fn ($u) => ! $u->twoFactorAuthEnabled())
                ->count();
        }

        $enrollmentRequired = User::where('is_active', true)
            ->where('status', UserStatus::ACTIVE)
            ->where('mfa_enrollment_required', true)
            ->count();

        return [
            [
                'label' => 'Total Active Users',
                'value' => $totalActive,
            ],
            [
                'label' => 'MFA Enabled',
                'value' => $mfaEnabled,
            ],
            [
                'label' => 'MFA Required Roles',
                'value' => $mfaRequired,
            ],
            [
                'label' => 'Required but Not Enabled',
                'value' => $requiredNotEnabled,
            ],
            [
                'label' => 'Forced Enrollment Required',
                'value' => $enrollmentRequired,
            ],
        ];
    }

    protected function getTableQuery(): Builder
    {
        $actor = auth()->user();
        $query = User::query();

        if ($actor->isSuperAdmin()) {
            return $query;
        }

        if ($actor->isAdmin()) {
            // Cannot view super_admin or other admin accounts to prevent lateral access
            return $query->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['super_admin', 'admin']);
            });
        }

        return $query->whereRaw('1=0');
    }

    public function table(Table $table): Table
    {
        $resetAction = Action::make('resetMfa')
            ->label('Reset MFA')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (User $record) => Gate::allows('resetMfa', $record))
            ->action(function (User $record) {
                try {
                    $service = new \App\Services\MfaService;
                    $service->resetMfa(auth()->user(), $record);

                    \Filament\Notifications\Notification::make()
                        ->title('MFA Reset Successfully')
                        ->body("Multi-Factor Authentication for {$record->email} has been reset.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    report($e);

                    \Filament\Notifications\Notification::make()
                        ->title('MFA Reset Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });

        SensitiveActionConfirmation::apply(
            action: $resetAction,
            level: SensitiveConfirmationLevel::PASSWORD_AND_PHRASE,
            phrase: 'RESET MFA',
            actionKey: 'reset_user_mfa'
        );

        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('mfa_requirement')
                    ->label('MFA Requirement')
                    ->badge()
                    ->state(fn (User $record) => $record->isMfaRequired() ? 'Mandatory' : 'Optional')
                    ->color(fn (string $state) => $state === 'Mandatory' ? 'danger' : 'gray'),

                TextColumn::make('mfa_status')
                    ->label('MFA Status')
                    ->badge()
                    ->state(fn (User $record) => match ($record->mfaState()) {
                        'enabled' => 'Enabled',
                        'pending_enrollment' => 'Pending Enrollment',
                        'disabled' => $record->isMfaRequired() ? 'Enrollment Required' : 'Not Enabled',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Enabled' => 'success',
                        'Pending Enrollment' => 'warning',
                        'Enrollment Required' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('mfa_confirmed_at')
                    ->label('Confirmed At')
                    ->dateTime()
                    ->placeholder('—'),

                IconColumn::make('mfa_enrollment_required')
                    ->label('Forced Enrollment')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('mfa_status')
                    ->label('MFA Status')
                    ->options([
                        'enabled' => 'MFA Enabled',
                        'disabled' => 'MFA Disabled',
                        'pending' => 'Pending Enrollment',
                        'required_not_enabled' => 'Mandatory but Not Enabled',
                        'enrollment_required' => 'Forced Enrollment Flag Set',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }

                        $mandatoryRoles = config('security.mfa.mandatory_roles', ['super_admin', 'admin', 'custodian', 'auditor']);

                        match ($data['value']) {
                            'enabled' => $query->whereNotNull('app_authentication_secret')->whereNotNull('mfa_confirmed_at'),
                            'disabled' => $query->whereNull('app_authentication_secret'),
                            'pending' => $query->whereNotNull('app_authentication_secret')->whereNull('mfa_confirmed_at'),
                            'required_not_enabled' => $query->where(function ($q) use ($mandatoryRoles) {
                                $q->whereNull('mfa_confirmed_at')
                                    ->where(function ($sub) use ($mandatoryRoles) {
                                        $sub->where('mfa_enrollment_required', true)
                                            ->orWhereHas('roles', function ($r) use ($mandatoryRoles) {
                                                $r->whereIn('name', $mandatoryRoles);
                                            });
                                    });
                            }),
                            'enrollment_required' => $query->where('mfa_enrollment_required', true),
                        };
                    }),

                SelectFilter::make('mfa_requirement')
                    ->label('MFA Requirement')
                    ->options([
                        'mandatory' => 'Mandatory',
                        'optional' => 'Optional',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return;
                        }

                        $mandatoryRoles = config('security.mfa.mandatory_roles', ['super_admin', 'admin', 'custodian', 'auditor']);

                        if ($data['value'] === 'mandatory') {
                            $query->where(function ($q) use ($mandatoryRoles) {
                                $q->where('mfa_enrollment_required', true)
                                    ->orWhereHas('roles', function ($r) use ($mandatoryRoles) {
                                        $r->whereIn('name', $mandatoryRoles);
                                    });
                            });
                        } else {
                            $query->where('mfa_enrollment_required', false)
                                ->whereDoesntHave('roles', function ($r) use ($mandatoryRoles) {
                                    $r->whereIn('name', $mandatoryRoles);
                                });
                        }
                    }),

                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Account Status'),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('viewMfaStatus')
                        ->label('View Status')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading('MFA Security Details')
                        ->modalDescription('Safe operational metadata of user MFA status.')
                        ->form([
                            \Filament\Forms\Components\TextInput::make('name')
                                ->disabled()
                                ->default(fn (User $record) => $record->name),
                            \Filament\Forms\Components\TextInput::make('email')
                                ->disabled()
                                ->default(fn (User $record) => $record->email),
                            \Filament\Forms\Components\TextInput::make('mfa_state')
                                ->label('MFA State')
                                ->disabled()
                                ->default(fn (User $record) => strtoupper($record->mfaState())),
                            \Filament\Forms\Components\TextInput::make('mfa_confirmed_at')
                                ->label('Confirmed At')
                                ->disabled()
                                ->default(fn (User $record) => $record->mfa_confirmed_at?->toDateTimeString() ?? 'N/A'),
                            \Filament\Forms\Components\Toggle::make('mfa_enrollment_required')
                                ->label('Forced Enrollment Required')
                                ->disabled()
                                ->default(fn (User $record) => $record->mfa_enrollment_required),
                        ])
                        ->modalSubmitAction(false),

                    Action::make('requireEnrollment')
                        ->label('Force Enrollment')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('warning')
                        ->visible(fn (User $record) => Gate::allows('update', $record) && ! $record->mfa_enrollment_required)
                        ->action(function (User $record) {
                            try {
                                $service = new \App\Services\MfaService;
                                $service->requireMfaEnrollment(auth()->user(), $record);

                                \Filament\Notifications\Notification::make()
                                    ->title('Enrollment Required')
                                    ->body("MFA enrollment is now forced for {$record->email}.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Action Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    $resetAction,
                ]),
            ]);
    }
}
