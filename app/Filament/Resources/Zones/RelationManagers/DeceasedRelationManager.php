<?php

namespace App\Filament\Resources\Zones\RelationManagers;

use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\Zones\ZoneResource;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeceasedRelationManager extends RelationManager
{
    protected static string $relationship = 'deceased';

    protected static ?string $relatedResource = ZoneResource::class;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $title = 'Registered Households (Deceased)';

    /**
     * Disable creation from this relation manager to keep it read-only.
     */
    public function canCreate(): bool
    {
        return false;
    }

    /**
     * The form is used by the ViewAction. All components are disabled.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Household Identity')
                    ->description('Primary registration and identification data.')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('full_name')
                                ->label('Full Name')
                                ->columnSpan(2)
                                ->disabled(),
                            TextInput::make('reg_no')
                                ->label('Registration ID')
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Biographical & Clinical Details')
                    ->icon('heroicon-m-user-minus')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('age')
                                ->label('Age at Death')
                                ->disabled(),
                            TextInput::make('vulnerability_status')
                                ->label('Vulnerability Status')
                                ->disabled(),
                            TextInput::make('occupation')
                                ->label('Last Known Occupation')
                                ->disabled(),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('death_cause')
                                ->label('Cause of Death')
                                ->disabled(),
                            TextInput::make('death_place')
                                ->label('Place of Passing')
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Location & Contact')
                    ->description('Physical residence and guardian information.')
                    ->icon('heroicon-m-map-pin')
                    ->schema([
                        Textarea::make('address')
                            ->label('Last Residential Address')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(),

                        Grid::make(2)->schema([
                            TextInput::make('guardian_name')
                                ->label('Guardian Name')
                                ->disabled(),
                            TextInput::make('guardian_phone')
                                ->label('Guardian Contact')
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Dependents Summary')
                    ->icon('heroicon-m-users')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('number_of_widows_left')
                                ->label('Widows Remaining')
                                ->disabled(),
                            TextInput::make('number_of_orphans_left')
                                ->label('Orphans Remaining')
                                ->disabled(),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vulnerability_status')
                    ->label('Vulnerability')
                    ->badge()
                    ->sortable(),

                TextColumn::make('widows_count')
                    ->counts('widows')
                    ->label('Widows')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('orphans_count')
                    ->counts('orphans')
                    ->label('Orphans')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('date_registered')
                    ->label('Registered')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('vulnerability_status')
                    ->options(VulnerabilityStatus::class),

                Filter::make('has_widows')
                    ->label('Has Widows')
                    ->query(fn (Builder $query) => $query->where('number_of_widows_left', '>', 0)),

                Filter::make('has_orphans')
                    ->label('Has Orphans')
                    ->query(fn (Builder $query) => $query->where('number_of_orphans_left', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Details')
                    ->icon('heroicon-m-eye')
                    ->color('gray'),
            ])
            ->toolbarActions([
                // No bulk actions to maintain read-only state
            ])
            ->emptyStateHeading('No households registered in this zone.')
            ->emptyStateDescription('Once a coordinator adds a deceased household head to this zone, they will appear here.');
    }
}
