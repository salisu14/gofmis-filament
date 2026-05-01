<?php
// app/Filament/Coordinator/Resources/ProjectResource.php

namespace App\Filament\Coordinator\Resources;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Filament\Coordinator\Concerns\ZoneScoped;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Coordinator\Resources\ProjectResource\Pages\ViewProject;
use App\Models\Project;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    use ZoneScoped;

    protected static ?string $model = Project::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static string|null|\UnitEnum $navigationGroup = 'Projects';
    protected static ?int $navigationSort = 5;

    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->whereHas('deceased', function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId);
        });
    }

    protected static function getRecordZoneId($record): ?string
    {
        return $record->deceased?->zone_id;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super_admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->hasAnyRole(['admin', 'super_admin'])) return true;

        return $record->deceased?->zone_id === $user->zone_id;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $coordinatorZoneId = auth()->user()?->zone_id;

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

                        Select::make('deceased_id')
                            ->label('Beneficiary Family')
                            ->relationship(
                                'deceased',
                                'full_name',
                                fn (Builder $query) => $query->when($coordinatorZoneId, fn ($q) => $q->where('zone_id', $coordinatorZoneId))
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->full_name} ({$record->reg_no})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

//                        Forms\Components\Select::make('deceased_id')
//                            ->label('Beneficiary Family')
//                            ->relationship('deceased', 'full_name', fn($q) =>
//                            $q->where('zone_id', $zoneId)  // Now $zoneId has correct value
//                            )
//                            ->searchable()
//                            ->preload()
//                            ->required(),

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
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
            'view' => ViewProject::route('/{record}'),
        ];
    }
}
