<?php

namespace App\Filament\Resources\Zones\RelationManagers;

use App\Models\ZoneCoordinatorHistory;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoordinatorHistoryRelationManager extends RelationManager
{
    /**
     * Relationship defined in the Zone model.
     * Ensure Zone.php has: public function coordinatorHistories() { return $this->hasMany(ZoneCoordinatorHistory::class, 'zone_id'); }
     */
    protected static string $relationship = 'coordinatorHistories';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Coordinator Assignment History';

    protected $listeners = ['refreshRelation' => '$refresh'];

    /**
     * View-only form for inspecting historical assignment details.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Assignment Context')
                    ->description('Details regarding this specific period of responsibility.')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\Select::make('user_id')
                                ->label('Coordinator')
                                ->relationship('coordinator', 'name')
                                ->disabled(),

                            Forms\Components\Select::make('changed_by')
                                ->label('Authorized By')
                                ->relationship('changer', 'name')
                                ->disabled(),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\DateTimePicker::make('assigned_at')
                                ->label('Start Date')
                                ->disabled(),

                            Forms\Components\DateTimePicker::make('unassigned_at')
                                ->label('End Date')
                                ->placeholder('Currently Active')
                                ->disabled(),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('coordinator.name')
                    ->label('Staff Member')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (ZoneCoordinatorHistory $record) => $record->isActive() ? 'Active' : 'Previous')
                    ->badge()
                    ->color(fn ($state) => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn ($state) => $state === 'Active' ? 'heroicon-m-check-circle' : 'heroicon-m-clock'),

                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Assigned')
                    ->dateTime('d M, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unassigned_at')
                    ->label('Relinquished')
                    ->dateTime('d M, Y H:i')
                    ->placeholder('Currently Active')
                    ->sortable(),

                Tables\Columns\TextColumn::make('changer.name')
                    ->label('Authorized By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Assignment Status')
                    ->placeholder('All Records')
                    ->trueLabel('Only Active')
                    ->falseLabel('Historical Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('unassigned_at'),
                        false: fn (Builder $query) => $query->whereNotNull('unassigned_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Preserving history for audit integrity
            ])
            ->emptyStateHeading('No assignment history found.')
            ->emptyStateDescription('History logs are generated automatically when a zone coordinator is updated.');
    }
}
