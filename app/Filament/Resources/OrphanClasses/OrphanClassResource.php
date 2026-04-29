<?php

namespace App\Filament\Resources\OrphanClasses;

use App\Filament\Resources\OrphanClasses\Pages\CreateOrphanClass;
use App\Filament\Resources\OrphanClasses\Pages\EditOrphanClass;
use App\Filament\Resources\OrphanClasses\Pages\ListOrphanClasses;
use App\Filament\Resources\OrphanClasses\Schemas\OrphanClassForm;
use App\Filament\Resources\OrphanClasses\Tables\OrphanClassesTable;
use App\Models\OrphanClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrphanClassResource extends Resource
{
    protected static ?string $model = OrphanClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return OrphanClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrphanClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrphanClasses::route('/'),
            'create' => CreateOrphanClass::route('/create'),
            'edit' => EditOrphanClass::route('/{record}/edit'),
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
