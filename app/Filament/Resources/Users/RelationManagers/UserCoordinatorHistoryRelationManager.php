<?php

namespace App\Filament\Resources\Users\RelationManagers;

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

class UserCoordinatorHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'coordinatorHistories';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Zone Assignment History';

    protected $listeners = ['refreshRelation' => '$refresh'];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Assignment Context')
                    ->description('Details regarding this specific period of zone responsibility.')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\Select::make('zone_id')
                                ->label('Zone')
                                ->relationship('zone', 'name')
                                ->disabled(),

                            Forms\Components\Select::make('changed_by')
                                ->label('Assigned By')
                                ->relationship('changer', 'name')
                                ->disabled(),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\DateTimePicker::make('assigned_at')
                                ->label('Assigned From')
                                ->disabled(),

                            Forms\Components\DateTimePicker::make('unassigned_at')
                                ->label('Assigned Until')
                                ->placeholder('Current Assignment')
                                ->disabled(),
                        ]),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason / Notes')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Assigned From')
                    ->dateTime('d M, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unassigned_at')
                    ->label('Assigned Until')
                    ->dateTime('d M, Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (ZoneCoordinatorHistory $record) => $record->isActive() ? 'Current' : 'Previous')
                    ->badge()
                    ->color(fn ($state) => $state === 'Current' ? 'success' : 'gray')
                    ->icon(fn ($state) => $state === 'Current' ? 'heroicon-m-check-circle' : 'heroicon-m-clock'),

                Tables\Columns\TextColumn::make('changer.name')
                    ->label('Assigned By')
                    ->placeholder('System')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Assignment Status')
                    ->placeholder('All Records')
                    ->trueLabel('Only Current')
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
                // Read-only audit trail
            ])
            ->emptyStateHeading('No zone assignment history found.')
            ->emptyStateDescription('History logs are generated automatically when a zone coordinator is assigned or reassigned.');
    }
}
