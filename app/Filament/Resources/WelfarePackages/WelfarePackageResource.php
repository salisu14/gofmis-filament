<?php

namespace App\Filament\Resources\WelfarePackages;

use App\Filament\Resources\WelfarePackages\Pages\CreateWelfarePackage;
use App\Filament\Resources\WelfarePackages\Pages\EditWelfarePackage;
use App\Filament\Resources\WelfarePackages\Pages\ListWelfarePackages;
use App\Filament\Resources\WelfarePackages\Schemas\WelfarePackageForm;
use App\Filament\Resources\WelfarePackages\Schemas\WelfarePackageInfolist;
use App\Filament\Resources\WelfarePackages\Tables\WelfarePackagesTable;
use App\Models\WelfarePackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WelfarePackageResource extends Resource
{
    protected static ?string $model = WelfarePackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Gift;

    protected static string|null|\UnitEnum $navigationGroup = 'Welfare Management';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return WelfarePackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WelfarePackagesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WelfarePackageInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\BeneficiariesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWelfarePackages::route('/'),
            'create' => CreateWelfarePackage::route('/create'),
            'edit' => EditWelfarePackage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['creator', 'items', 'items.item', 'items.category']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Status' => $record->status->label(),
            'Period' => "{$record->start_date->format('M d')} - {$record->end_date->format('M d, Y')}",
        ];
    }
}
