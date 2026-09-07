<?php

namespace App\Filament\RelationManagers;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;
use App\Enums\BiometricOperation;
use App\Services\Biometrics\BiometricAuditService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FingerprintsRelationManager extends RelationManager
{
    protected static string $relationship = 'fingerprints';

    protected static ?string $recordTitleAttribute = 'finger_position';

    protected static ?string $title = 'Biometric Fingerprints';

    /**
     * Whether the given owner beneficiary's biometric surface may be accessed
     * by the current user. Admins/super admins pass; a coordinator must manage
     * the beneficiary's zone (beneficiary -> deceased -> zone).
     */
    protected function biometricAccessAllowed(Model $owner): bool
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        if (! $user) {
            return false;
        }

        // Canonical zone ownership path: beneficiary -> deceased_household -> zone.
        // Resolve the zone without global scopes so out-of-zone beneficiaries
        // are not mis-characterised as "no zone" by the coordinated-zone scope.
        $zoneId = null;

        if ($owner instanceof \App\Models\Widow || $owner instanceof \App\Models\Orphan) {
            $zoneId = $owner->deceased()->withoutGlobalScopes()->value('zone_id');
        }

        if ($zoneId === null) {
            // No resolvable zone (or the beneficiary does not carry one) must not
            // grant a coordinator broad access.
            return false;
        }

        return $user->managesZone($zoneId);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]); // We use custom actions instead of default forms
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('finger_position')
                    ->label('Position')
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->searchable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'mock' => 'Mock Hardware',
                        'hardware' => 'Hardware Device',
                        default => str($state ?? 'Unknown')->title(),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'mock' => 'warning',
                        'hardware' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quality_score')
                    ->label('Quality')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state}%" : 'N/A')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('enrolled_at')
                    ->label('Enrolled Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_verified_at')
                    ->label('Last Verified')
                    ->dateTime()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('enroller.name')
                    ->label('Enrolled By'),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->label('Revoked At')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('revocation_reason')
                    ->label('Revocation Reason')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->default(),
            ])
            ->headerActions([
                Action::make('enroll')
                    ->label('Enroll Fingerprint')
                    ->icon('heroicon-o-finger-print')
                    ->visible(function () {
                        $user = auth()->user();

                        return $user?->can('biometrics.enroll')
                            && $this->biometricAccessAllowed($this->getOwnerRecord());
                    })
                    ->form([
                        Forms\Components\Select::make('finger_position')
                            ->label('Finger Position')
                            ->options([
                                'right_thumb' => 'Right Thumb',
                                'right_index' => 'Right Index',
                                'right_middle' => 'Right Middle',
                                'right_ring' => 'Right Ring',
                                'right_little' => 'Right Little',
                                'left_thumb' => 'Left Thumb',
                                'left_index' => 'Left Index',
                                'left_middle' => 'Left Middle',
                                'left_ring' => 'Left Ring',
                                'left_little' => 'Left Little',
                            ])
                            ->required()
                            ->rules([
                                fn (RelationManager $livewire) => function (string $attribute, $value, \Closure $fail) use ($livewire) {
                                    $owner = $livewire->getOwnerRecord();
                                    $exists = $owner->fingerprints()
                                        ->where('finger_position', $value)
                                        ->where('is_active', true)
                                        ->exists();

                                    if ($exists) {
                                        $fail('An active enrollment already exists for this finger position.');
                                    }
                                },
                            ]),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        abort_unless(auth()->user()->can('biometrics.enroll'), 403);
                        abort_unless($livewire->biometricAccessAllowed($livewire->getOwnerRecord()), 403);

                        try {
                            $client = app(FingerprintDeviceClientInterface::class);
                            $health = $client->health();

                            if ($health['status'] !== 'ok') {
                                Notification::make()
                                    ->title('Scanner Unavailable')
                                    ->body($health['message'] ?? 'The biometric scanner bridge is not responding.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $result = $client->enroll();

                            if ($result['status'] !== 'ok') {
                                Notification::make()
                                    ->title('Enrollment Failed')
                                    ->body($result['message'] ?? 'Failed to capture fingerprint.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if (($result['quality'] ?? 0) < 60) {
                                Notification::make()
                                    ->title('Low Quality')
                                    ->body('The fingerprint quality is too low. Please try again.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $owner = $livewire->getOwnerRecord();
                            $print = $owner->fingerprints()->create([
                                'finger_position' => $data['finger_position'],
                                'encrypted_template' => $result['template'],
                                'template_format' => $result['format'] ?? null,
                                'quality_score' => $result['quality'] ?? null,
                                'source' => $result['source'] ?? 'hardware',
                                'device_manufacturer' => $result['device_manufacturer'] ?? null,
                                'device_model' => $result['device_model'] ?? null,
                                'device_serial' => $result['device_serial'] ?? null,
                                'sdk_version' => $result['sdk_version'] ?? null,
                                'enrolled_by' => auth()->id(),
                                'is_active' => true,
                            ]);

                            // Governance/audit: structured, append-only event.
                            app(BiometricAuditService::class)->record(
                                BiometricOperation::ENROLLMENT,
                                $owner,
                                $print,
                                result: 'success',
                                extra: [
                                    'source_client' => $result['source'] ?? 'hardware',
                                    'request_id' => $result['request_id'] ?? null,
                                ],
                            );

                            Notification::make()
                                ->title('Fingerprint Enrolled Successfully')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('System Error')
                                ->body('An unexpected error occurred during biometric enrollment.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('identifyBeneficiary')
                    ->label('Identify Beneficiary')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(function () {
                        $user = auth()->user();

                        return $user?->can('biometrics.identify')
                            && $this->biometricAccessAllowed($this->getOwnerRecord());
                    })
                    ->requiresConfirmation()
                    ->action(function () {
                        $user = auth()->user();

                        try {
                            $outcome = app(\App\Services\Biometrics\BiometricIdentificationService::class)
                                ->identify($user);

                            match ($outcome['status']) {
                                'match' => Notification::make()
                                    ->title('Beneficiary Identified')
                                    ->body(($outcome['beneficiary']?->display_name ?? 'Beneficiary').' matched on fingerprint.')
                                    ->success()
                                    ->send(),
                                'no_match' => Notification::make()
                                    ->title('No Match')
                                    ->body('No matching beneficiary was found.')
                                    ->warning()
                                    ->send(),
                                default => Notification::make()
                                    ->title('Identification Failed')
                                    ->body($outcome['message'] ?? 'Identification could not be completed.')
                                    ->danger()
                                    ->send(),
                            };
                        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                            Notification::make()->title('Not Authorised')->danger()->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Identification Error')
                                ->body('Fingerprint identification could not be completed.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('verifyIdentity')
                    ->label('Verify Identity')
                    ->icon('heroicon-o-bolt')
                    ->visible(function (?Model $record) {
                        $user = auth()->user();
                        if (! $user?->can('biometrics.verify')) {
                            return false;
                        }

                        return $record && $record->is_active && $this->biometricAccessAllowed($this->getOwnerRecord());
                    })
                    ->requiresConfirmation()
                    ->action(function (Model $record) {
                        $beneficiary = $this->getOwnerRecord();
                        $user = auth()->user();

                        try {
                            $outcome = app(\App\Services\Biometrics\BiometricVerificationService::class)
                                ->verify($beneficiary, $record, $user);

                            match ($outcome['status']) {
                                'match' => Notification::make()
                                    ->title('Identity Verified')
                                    ->body('Fingerprint match confirmed.')
                                    ->success()
                                    ->send(),
                                'no_match' => Notification::make()
                                    ->title('No Match')
                                    ->body('This fingerprint does not match the enrolled template.')
                                    ->warning()
                                    ->send(),
                                default => Notification::make()
                                    ->title('Verification Failed')
                                    ->body($outcome['message'] ?? 'Verification could not be completed.')
                                    ->danger()
                                    ->send(),
                            };
                        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                            Notification::make()->title('Not Authorised')->danger()->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Verification Error')
                                ->body('Fingerprint verification could not be completed.')
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn (?Model $record) => $record && $record->is_active && auth()->user()->can('biometrics.revoke'))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('revocation_reason')
                            ->label('Reason for Revocation')
                            ->required(),
                    ])
                    ->action(function (array $data, Model $record) {
                        abort_unless(auth()->user()->can('biometrics.revoke'), 403);

                        $record->update([
                            'is_active' => false,
                            'revoked_at' => now(),
                            'revocation_reason' => $data['revocation_reason'],
                        ]);

                        // Governance/audit: structured, append-only event.
                        $beneficiary = $record->beneficiary;
                        if ($beneficiary) {
                            app(BiometricAuditService::class)->record(
                                BiometricOperation::REVOCATION,
                                $beneficiary,
                                $record,
                                result: 'revoked',
                                reason: $data['revocation_reason'],
                            );
                        }

                        Notification::make()
                            ->title('Fingerprint Revoked')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No Biometrics Enrolled')
            ->emptyStateDescription('Enroll a fingerprint to verify identity securely.');
    }
}
