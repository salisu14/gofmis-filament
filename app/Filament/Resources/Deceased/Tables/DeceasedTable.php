<?php

namespace App\Filament\Resources\Deceased\Tables;

use App\Enums\VulnerabilityStatus;
use App\Models\Deceased;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                    ->description(fn ($record) => "Reg: {$record->reg_no}"),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('vulnerability_status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('zone.name')
                    ->label('Location')
                    ->description(fn ($record) => $record->zone?->town?->name . ', ' . $record->zone?->town?->city?->name),

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
                // 2. Query by Vulnerability Status
                SelectFilter::make('vulnerability_status')
                    ->options(VulnerabilityStatus::class),

                // 6. Query by Zone (A1 - A13)
                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name')
                    ->searchable()
                    ->preload(),

                // 4. Query by Specific Cause of Death
                SelectFilter::make('death_cause')
                    ->label('Cause of Death')
                    ->options(fn () => Deceased::query()
                        ->distinct()
                        ->whereNotNull('death_cause')
                        ->pluck('death_cause', 'death_cause')
                        ->toArray())
                    ->searchable(),

                // 3. Query by Date of Registration (Year)
                Filter::make('registration_year')
                    ->schema([
                        Select::make('year')
                            ->label('Registration Year')
                            ->options(array_combine(range(date('Y'), 2010), range(date('Y'), 2010)))
                            ->placeholder('Select Year'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['year'], fn ($q) => $q->whereYear('date_registered', $data['year']));
                    }),

                // 1 & 5. Query by Number of Orphans and Widows
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
                            ->when($data['min_orphans'], fn ($q) => $q->where('number_of_orphans_left', '>=', $data['min_orphans']))
                            ->when($data['min_widows'], fn ($q) => $q->where('number_of_widows_left', '>=', $data['min_widows']));
                    }),

                // 8. Query by Age (Life Expectancy Analysis)
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
                            ->when($data['age_from'], fn ($q) => $q->where('age', '>=', $data['age_from']))
                            ->when($data['age_to'], fn ($q) => $q->where('age', '<=', $data['age_to']));
                    }),

                // 7. Query by Intervention Received
                TernaryFilter::make('has_interventions')
                    ->label('Intervention Received')
                    ->queries(
                        true: fn ($query) => $query->whereHas('interventions'),
                        false: fn ($query) => $query->whereDoesntHave('interventions'),
                    ),

                TernaryFilter::make('has_death_cert')
                    ->label('Death Certificate'),
            ])->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
