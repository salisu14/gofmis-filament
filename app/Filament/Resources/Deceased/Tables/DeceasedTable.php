<?php

namespace App\Filament\Resources\Deceased\Tables;

use App\Enums\VulnerabilityStatus;
use App\Models\Deceased;
use App\Models\Zone;
use App\Services\Deceased\ZoneTransferService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class DeceasedTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->deferLoading()
            ->groups([
                Group::make('zone.name')
                    ->label('Zone'),

                // FIX: Used getTitleFromRecordUsing instead of getTitleUsing
                Group::make('vulnerability_status')
                    ->label('Vulnerability Status')
                    ->getTitleFromRecordUsing(fn (Deceased $record): string => $record->vulnerability_status->getLabel())
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->description(fn ($record) => "Reg: {$record->reg_no}"),

                TextColumn::make('age_at_death')
                    ->label('Age at Death')
                    ->suffix(' years')
                    ->numeric()
                    ->toggleable()
                    ->state(fn (Deceased $record) => $record->age_at_death),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('vulnerability_status')
                    ->label('Vulnerability Status')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->description(fn ($record) => $record->zone?->town?->name.', '.$record->zone?->town?->city?->name),

                TextColumn::make('widows_count')
                    ->counts('widows')
                    ->label('Registered Widows')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('orphans_count')
                    ->counts('orphans')
                    ->label('Registered Orphans')
                    ->badge()
                    ->color('info'),

                TextColumn::make('date_of_death')
                    ->label('Date of Death')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('date_registered')
                    ->label('Date Registered')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                // A. Zone
                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name')
                    ->searchable()
                    ->preload(),

                // B. Vulnerability status
                SelectFilter::make('vulnerability_status')
                    ->label('Vulnerability Status')
                    ->options(VulnerabilityStatus::class),

                // C. Cause of death
                SelectFilter::make('death_cause')
                    ->label('Cause of Death')
                    ->options(function () {
                        $canonical = \App\Services\Deceased\CauseOfDeathCatalog::options();
                        $dbValues = Deceased::query()
                            ->distinct()
                            ->whereNotNull('death_cause')
                            ->pluck('death_cause', 'death_cause')
                            ->toArray();

                        return array_merge($canonical, $dbValues);
                    })
                    ->searchable(),

                // D. Date of death range (from/to)
                Filter::make('date_of_death_range')
                    ->label('Date of Death (Range)')
                    ->schema([
                        DatePicker::make('dod_from')
                            ->label('Death Date From')
                            ->native(false)
                            ->maxDate('today'),
                        DatePicker::make('dod_to')
                            ->label('Death Date To')
                            ->native(false)
                            ->maxDate('today'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['dod_from'], fn ($q) => $q->whereDate('date_of_death', '>=', $data['dod_from']))
                            ->when($data['dod_to'], fn ($q) => $q->whereDate('date_of_death', '<=', $data['dod_to']));
                    })
                    ->columns(2),

                // E. Year of death
                Filter::make('death_year')
                    ->schema([
                        Select::make('year')
                            ->label('Year of Death')
                            ->options(array_combine(range(date('Y'), 1990), range(date('Y'), 1990)))
                            ->placeholder('Select Year'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['year'], fn ($q) => $q->whereYear('date_of_death', $data['year']));
                    }),

                // F. GOF Registration date range
                Filter::make('registration_date_range')
                    ->label('Registration Date (Range)')
                    ->schema([
                        DatePicker::make('reg_from')
                            ->label('Registered From')
                            ->native(false),
                        DatePicker::make('reg_to')
                            ->label('Registered To')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['reg_from'], fn ($q) => $q->whereDate('date_registered', '>=', $data['reg_from']))
                            ->when($data['reg_to'], fn ($q) => $q->whereDate('date_registered', '<=', $data['reg_to']));
                    })
                    ->columns(2),

                // F2. Registration year (quick select)
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

                // G. Age-at-death range (uses stored legacy age OR computed — filters on `age` column for DB-level)
                Filter::make('age_at_death_range')
                    ->label('Age at Death (Range)')
                    ->schema([
                        TextInput::make('age_from')
                            ->label('Age From')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('age_to')
                            ->label('Age To')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['age_from'], fn ($q) => $q->where('age', '>=', $data['age_from']))
                            ->when($data['age_to'], fn ($q) => $q->where('age', '<=', $data['age_to']));
                    })
                    ->columns(2),

                // H. Declared (historical) orphan count at registration
                // Semantics: number_of_orphans_left = count declared by family at registration time
                Filter::make('declared_orphans')
                    ->label('Declared Orphans (at Reg.)')
                    ->schema([
                        TextInput::make('min_orphans_declared')
                            ->label('Min. Declared Orphans')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            isset($data['min_orphans_declared']) && $data['min_orphans_declared'] !== '',
                            fn ($q) => $q->where('number_of_orphans_left', '>=', $data['min_orphans_declared'])
                        );
                    }),

                // I. Declared (historical) widow count at registration
                Filter::make('declared_widows')
                    ->label('Declared Widows (at Reg.)')
                    ->schema([
                        TextInput::make('min_widows_declared')
                            ->label('Min. Declared Widows')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            isset($data['min_widows_declared']) && $data['min_widows_declared'] !== '',
                            fn ($q) => $q->where('number_of_widows_left', '>=', $data['min_widows_declared'])
                        );
                    }),

                // J. Current registered orphan count (live relationship count)
                // Semantics: how many orphan records are currently linked in the system
                Filter::make('registered_orphans')
                    ->label('Registered Orphans (Live)')
                    ->schema([
                        TextInput::make('min_orphans_registered')
                            ->label('Min. Registered Orphans')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            isset($data['min_orphans_registered']) && $data['min_orphans_registered'] !== '',
                            fn ($q) => $q->has('orphans', '>=', $data['min_orphans_registered'])
                        );
                    }),

                // K. Current registered widow count (live relationship count)
                Filter::make('registered_widows')
                    ->label('Registered Widows (Live)')
                    ->schema([
                        TextInput::make('min_widows_registered')
                            ->label('Min. Registered Widows')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            isset($data['min_widows_registered']) && $data['min_widows_registered'] !== '',
                            fn ($q) => $q->has('widows', '>=', $data['min_widows_registered'])
                        );
                    }),

                // L. Intervention received filter
                // Semantics: A deceased family "has an intervention" when at least one of their
                // registered orphans has a fulfilled (status=approved OR status=disbursed)
                // InterventionRequest. This traces: Deceased → orphans → interventionRequests (status).
                // Note: Deceased has no direct intervention relationship; orphans are the beneficiaries.
                TernaryFilter::make('has_intervention')
                    ->label('Orphan Intervention Received')
                    ->placeholder('Any')
                    ->trueLabel('With Approved/Disbursed Intervention')
                    ->falseLabel('Without Any Intervention')
                    ->queries(
                        true: fn ($query) => $query->whereHas(
                            'orphans.interventionRequests',
                            fn ($q) => $q->whereIn('status', ['approved', 'disbursed'])
                        ),
                        false: fn ($query) => $query->whereDoesntHave('orphans.interventionRequests'),
                        blank: fn ($query) => $query,
                    ),

                // Filter deceased by specific intervention request statuses
                SelectFilter::make('intervention_status')
                    ->label('Orphan Intervention Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'disbursed' => 'Disbursed',
                        'rejected' => 'Rejected',
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'],
                            fn ($q, $status) => $q->whereHas(
                                'orphans.interventionRequests',
                                fn ($q) => $q->where('status', $status)
                            )
                        );
                    }),

                // Welfare package filter (many-to-many via welfare_beneficiaries)
                SelectFilter::make('welfare_packages')
                    ->label('Welfare Package')
                    ->relationship('welfarePackages', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // Has any welfare package
                TernaryFilter::make('has_welfare_package')
                    ->label('Has Welfare Package')
                    ->placeholder('Any')
                    ->trueLabel('With Welfare Package')
                    ->falseLabel('Without Welfare Package')
                    ->queries(
                        true: fn ($query) => $query->whereHas('welfarePackages'),
                        false: fn ($query) => $query->whereDoesntHave('welfarePackages'),
                        blank: fn ($query) => $query,
                    ),

                TernaryFilter::make('has_death_cert')
                    ->label('Death Certificate'),
            ])->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
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
                        ->visible(fn (Deceased $record): bool => $record->zone_id !== null
                        )
                        ->schema([
                            Select::make('to_zone_id')
                                ->label('New Zone')
                                ->options(fn (Deceased $record): array => Zone::where('id', '!=', $record->zone_id)
                                    ->pluck('name', 'id')
                                    ->toArray()
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText(fn (Deceased $record): string => "Current zone: {$record->zone?->name}"
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
                                    ->body('An unexpected error occurred: '.$e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    // ============================================
                ]),
            ])
            ->filtersFormColumns(2)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
