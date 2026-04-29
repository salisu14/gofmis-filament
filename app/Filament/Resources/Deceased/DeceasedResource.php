<?php

namespace App\Filament\Resources\Deceased;

use App\Filament\Resources\Deceased\Pages\CreateDeceased;
use App\Filament\Resources\Deceased\Pages\EditDeceased;
use App\Filament\Resources\Deceased\Pages\ListDeceaseds;
use App\Filament\Resources\Deceased\Pages\ViewDeceased;
use App\Filament\Resources\Deceased\Schemas\DeceasedForm;
use App\Filament\Resources\Deceased\Schemas\DeceasedInfolist;
use App\Filament\Resources\Deceased\Tables\DeceasedTable;
use App\Models\Deceased;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeceasedResource extends Resource
{
    protected static ?string $model = Deceased::class;

    protected static ?string $slug = 'deceaseds';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    protected static function getRecordZoneId($record): ?string
    {
        return $record->zone_id;
    }


    public static function form(Schema $schema): Schema
    {
        return DeceasedForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeceasedInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeceasedTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WidowsRelationManager::class,
            RelationManagers\OrphansRelationManager::class,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'first_name',
            'middle_name',
            'last_name',
            'full_name',
            'reg_no',
            'nin',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->full_name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Reg No' => $record->reg_no,
            'NIN' => $record->nin,
            'Zone' => $record->zone?->name,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeceaseds::route('/'),
            'create' => CreateDeceased::route('/create'),
            'view' => ViewDeceased::route('/{record}'),
            'edit' => EditDeceased::route('/{record}/edit'),
        ];
    }
}
