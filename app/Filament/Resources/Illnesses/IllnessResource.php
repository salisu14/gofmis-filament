<?php

namespace App\Filament\Resources\Illnesses;

use App\Filament\Resources\Illnesses\Pages\CreateIllness;
use App\Filament\Resources\Illnesses\Pages\EditIllness;
use App\Filament\Resources\Illnesses\Pages\ListIllnesses;
use App\Filament\Resources\Illnesses\Schemas\IllnessForm;
use App\Filament\Resources\Illnesses\Tables\IllnessesTable;
use App\Models\Illness;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IllnessResource extends Resource
{
    protected static ?string $model = Illness::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return IllnessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IllnessesTable::configure($table);
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
            'index' => ListIllnesses::route('/'),
            'create' => CreateIllness::route('/create'),
            'edit' => EditIllness::route('/{record}/edit'),
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
