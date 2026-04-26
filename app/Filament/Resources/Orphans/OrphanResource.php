<?php

namespace App\Filament\Resources\Orphans;

use App\Filament\Resources\Orphans\Pages\CreateOrphan;
use App\Filament\Resources\Orphans\Pages\EditOrphan;
use App\Filament\Resources\Orphans\Pages\ListOrphans;
use App\Filament\Resources\Orphans\Pages\ViewOrphan;
use App\Filament\Resources\Orphans\Schemas\OrphanForm;
use App\Filament\Resources\Orphans\Schemas\OrphanInfolist;
use App\Filament\Resources\Orphans\Tables\OrphansTable;
use App\Models\Orphan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrphanResource extends Resource
{
    protected static ?string $model = Orphan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return OrphanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrphanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrphansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PrescriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrphans::route('/'),
            'create' => CreateOrphan::route('/create'),
            'view' => ViewOrphan::route('/{record}'),
            'edit' => EditOrphan::route('/{record}/edit'),
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
