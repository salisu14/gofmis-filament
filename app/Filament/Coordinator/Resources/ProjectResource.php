<?php
// app/Filament/Coordinator/Resources/ProjectResource.php

namespace App\Filament\Coordinator\Resources;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-building-office-2';
    protected static string|null|\UnitEnum $navigationGroup = 'Projects';
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $zoneId = auth()->user()?->zone_id;

        if (!$zoneId || auth()->user()?->hasAnyRole(['admin', 'super_admin'])) {
            return $query;
        }

        return $query->where('zone_id', $zoneId);
    }

    public static function form(Schema $schema): Schema
    {
        $zoneId = auth()->user()?->zone_id;

        return $schema
            ->schema([
                Section::make('Project Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->options(collect(ProjectType::cases())->mapWithKeys(
                                fn($type) => [$type->value => $type->label()]
                            ))
                            ->required(),

                        Forms\Components\Hidden::make('zone_id')
                            ->default($zoneId),

                        Forms\Components\Select::make('deceased_id')
                            ->label('Beneficiary Family')
                            ->relationship('deceased', 'full_name', fn($q) =>
                            $q->where('zone_id', $zoneId)
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('location_address')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Budget Request')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('budget_allocated')
                            ->label('Requested Budget (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->required(),

                        Forms\Components\DatePicker::make('expected_completion_date')
                            ->label('Target Completion'),
                    ]),

                Forms\Components\Hidden::make('status')
                    ->default(ProjectStatus::PLANNING->value),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state->label()),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state->color()),

                Tables\Columns\TextColumn::make('budget_allocated')
                    ->money('NGN')
                    ->label('Budget'),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%'),

                Tables\Columns\TextColumn::make('expected_completion_date')
                    ->date('M d, Y'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn($record) => in_array($record->status->value, ['planning', 'approved'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'view' => Pages\ViewProject::route('/{record}'),
        ];
    }
}
