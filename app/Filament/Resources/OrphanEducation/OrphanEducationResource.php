<?php

namespace App\Filament\Resources\OrphanEducation;

use App\Filament\Resources\OrphanEducation\Pages\CreateOrphanEducation;
use App\Filament\Resources\OrphanEducation\Pages\EditOrphanEducation;
use App\Filament\Resources\OrphanEducation\Pages\ListOrphanEducation;
use App\Filament\Resources\OrphanEducation\Pages\ViewOrphanEducation;
use App\Filament\Resources\OrphanEducation\Schemas\OrphanEducationForm;
use App\Filament\Resources\OrphanEducation\Schemas\OrphanEducationInfolist;
use App\Filament\Resources\OrphanEducation\Tables\OrphanEducationTable;
use App\Models\OrphanEducation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrphanEducationResource extends Resource
{
    protected static ?string $model = OrphanEducation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OrphanEducationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrphanEducationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrphanEducationTable::configure($table);
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
            'index' => ListOrphanEducation::route('/'),
            'create' => CreateOrphanEducation::route('/create'),
            'view' => ViewOrphanEducation::route('/{record}'),
            'edit' => EditOrphanEducation::route('/{record}/edit'),
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
