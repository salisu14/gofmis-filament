<?php

namespace App\Filament\Resources\Deceased\Tables;

use App\Enums\VulnerabilityStatus;
use App\Models\Deceased;
use App\Models\Zone;
use App\Services\Deceased\ZoneTransferService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DeceasedTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->description(fn($record) => "Reg: {$record->reg_no}"),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('vulnerability_status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('zone.name')
                    ->label('Location')
                    ->description(fn($record) => $record->zone?->town?->name . ', ' . $record->zone?->town?->city?->name),

                TextColumn::make('orphans_count')
                    ->counts('orphans')
                    ->label('Orphans')
                    ->badge()
                    ->color('info'),

                TextColumn::make('widows_count')
                    ->counts('widows')
                    ->label('Widows')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('date_registered')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vulnerability_status')
                    ->options(VulnerabilityStatus::class),

                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('death_cause')
                    ->label('Cause of Death')
                    ->options(fn() => Deceased::query()
                        ->distinct()
                        ->whereNotNull('death_cause')
                        ->pluck('death_cause', 'death_cause')
                        ->toArray())
                    ->searchable(),

                Filter::make('registration_year')
                    ->schema([
                        Select::make('year')
                            ->label('Registration Year')
                            ->options(array_combine(range(date('Y'), 2010), range(date('Y'), 2010)))
                            ->placeholder('Select Year'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['year'], fn($q) => $q->whereYear('date_registered', $data['year']));
                    }),

                Filter::make('dependents_count')
                    ->schema([
                        TextInput::make('min_orphans')
                            ->label('Min. Orphans')
                            ->numeric(),
                        TextInput::make('min_widows')
                            ->label('Min. Widows')
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['min_orphans'], fn($q) => $q->where('number_of_orphans_left', '>=', $data['min_orphans']))
                            ->when($data['min_widows'], fn($q) => $q->where('number_of_widows_left', '>=', $data['min_widows']));
                    }),

                Filter::make('age_analysis')
                    ->schema([
                        TextInput::make('age_from')
                            ->label('Age From')
                            ->numeric(),
                        TextInput::make('age_to')
                            ->label('Age To')
                            ->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['age_from'], fn($q) => $q->where('age', '>=', $data['age_from']))
                            ->when($data['age_to'], fn($q) => $q->where('age', '<=', $data['age_to']));
                    }),

                TernaryFilter::make('has_interventions')
                    ->label('Intervention Received')
                    ->queries(
                        true: fn($query) => $query->whereHas('interventions'),
                        false: fn($query) => $query->whereDoesntHave('interventions'),
                    ),

                TernaryFilter::make('has_death_cert')
                    ->label('Death Certificate'),
            ])->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                // ============================================
                // FIXED: Transfer Zone Action
                // ============================================
                Action::make('transfer_zone')
                    ->label('Transfer Zone')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')

                    // Only show if deceased has a zone assigned
                    ->visible(fn(Deceased $record): bool => $record->zone_id !== null
                    )
                    ->schema([
                        Select::make('to_zone_id')
                            ->label('New Zone')
                            ->options(fn(Deceased $record): array => Zone::where('id', '!=', $record->zone_id)
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText(fn(Deceased $record): string => "Current zone: {$record->zone?->name}"
                            ),

                        Textarea::make('reason')
                            ->label('Reason for Transfer')
                            ->required()
                            ->minLength(10)
                            ->maxLength(500)
                            ->placeholder('Explain why this family is being transferred...'),
                    ])

                    // Modal configuration
                    ->modalHeading('Transfer Family to Another Zone')
                    ->modalDescription('This will move the deceased record and all associated orphans and widows to the selected zone.')
                    ->modalSubmitActionLabel('Transfer Now')
                    ->modalIcon('heroicon-o-arrows-right-left')
                    ->modalIconColor('warning')

                    // Confirmation before proceeding
                    ->requiresConfirmation()

                    // The action handler
                    ->action(function (Deceased $record, array $data) {
                        try {
                            $transfer = app(ZoneTransferService::class)->transfer(
                                deceased: $record,
                                toZoneId: $data['to_zone_id'],
                                reason: $data['reason'],
                                performedBy: auth()->id(),
                            );

                            Notification::make()
                                ->title('Zone Transfer Successful')
                                ->body("Family transferred to {$transfer->toZone->name}. Transfer ID: {$transfer->id}")
                                ->success()
                                ->send();

                        } catch (\InvalidArgumentException $e) {
                            // Same zone transfer attempt
                            Notification::make()
                                ->title('Transfer Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                        } catch (\Exception $e) {
                            // Any other error
                            Notification::make()
                                ->title('Transfer Failed')
                                ->body('An unexpected error occurred: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                // ============================================
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
