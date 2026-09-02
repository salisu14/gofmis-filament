<?php

namespace App\Filament\RelationManagers;

use App\Contracts\Biometrics\FingerprintDeviceClientInterface;
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
                    ->visible(fn () => auth()->user()->can('biometrics.enroll'))
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
                            $owner->fingerprints()->create([
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
            ])
            ->actions([
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
