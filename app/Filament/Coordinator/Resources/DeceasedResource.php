<?php
// app/Filament/Coordinator/Resources/DeceasedResource.php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Concerns\ZoneScoped;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\CreateDeceased;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\EditDeceased;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\ViewDeceased;
use App\Filament\Coordinator\Resources\DeceasedResource\Pages\ListDeceaseds;
use App\Models\Deceased;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeceasedResource extends Resource
{
     use ZoneScoped;

    protected static ?string $model = Deceased::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-user-minus';
    protected static string|null|\UnitEnum $navigationGroup = 'Beneficiary Registration';
    protected static ?int $navigationSort = 1;

    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    protected static function getRecordZoneId($record): ?string
    {
        return $record->zone_id;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super_admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user) return false;

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $record->zone_id === $user->zone_id;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $coordinatorZoneId = auth()->user()?->zone_id;

        return $schema
            ->schema([
                Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('middle_name'),
                        TextInput::make('nin')
                            ->label('NIN')
                            ->unique(ignoreRecord: true),
                        DatePicker::make('date_of_death')->required(),
                        TextInput::make('death_cause'),
                        TextInput::make('age')
                            ->numeric(),
                    ]),

                Section::make('Family Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number_of_widows_left')
                            ->numeric()
                            ->default(0),
                        TextInput::make('number_of_orphans_left')
                            ->numeric()
                            ->default(0),
                    ]),

                Hidden::make('zone_id')
                    ->default($coordinatorZoneId),

                Hidden::make('registered_by')
                    ->default(auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('reg_no'),
                Tables\Columns\TextColumn::make('date_of_death')->date(),
                Tables\Columns\TextColumn::make('widows_count')
                    ->counts('widows'),
                Tables\Columns\TextColumn::make('orphans_count')
                    ->counts('orphans'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y'),
            ])
            ->filters([
                Tables\Filters\Filter::make('recent')
                    ->label('This Month')
                    ->query(fn($q) => $q->whereMonth('created_at', now()->month)),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeceaseds::route('/'),
            'create' => CreateDeceased::route('/create'),
            'edit' => EditDeceased::route('/{record}/edit'),
            'view' => ViewDeceased::route('/{record}'),
        ];
    }
}
