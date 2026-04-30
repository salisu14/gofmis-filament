<?php

namespace App\Filament\Resources\Widows;

use App\Filament\Resources\Widows\Pages\CreateWidow;
use App\Filament\Resources\Widows\Pages\EditWidow;
use App\Filament\Resources\Widows\Pages\ListWidows;
use App\Filament\Resources\Widows\Pages\ViewWidow;
use App\Filament\Resources\Widows\Schemas\WidowForm;
use App\Filament\Resources\Widows\Schemas\WidowInfolist;
use App\Filament\Resources\Widows\Tables\WidowsTable;
use App\Models\Widow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WidowResource extends Resource
{
    protected static ?string $model = Widow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return WidowForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WidowInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WidowsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PrescriptionsRelationManager::class,
//            RelationManagers\LoansRelationManager::class,
//            RelationManagers\WidowLoansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWidows::route('/'),
            'create' => CreateWidow::route('/create'),
            'view' => ViewWidow::route('/{record}'),
            'edit' => EditWidow::route('/{record}/edit'),
        ];
    }

    public static function isGloballySearchable(): bool
    {
        return true;
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
        return $record->full_name ?? $record->name ?? 'Record';
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Reg No' => $record->reg_no ?? null,
            'NIN' => $record->nin ?? null,
            'Class' => $record->orphanClass->name ?? null,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
