<?php

namespace App\Filament\Resources\InterventionTypes;

use App\Filament\Resources\InterventionTypes\Pages\CreateInterventionType;
use App\Filament\Resources\InterventionTypes\Pages\EditInterventionType;
use App\Filament\Resources\InterventionTypes\Pages\ListInterventionTypes;
use App\Filament\Resources\InterventionTypes\Schemas\InterventionTypeForm;
use App\Filament\Resources\InterventionTypes\Tables\InterventionTypesTable;
use App\Models\InterventionType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InterventionTypeResource extends Resource
{
    protected static ?string $model = InterventionType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return InterventionTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InterventionTypesTable::configure($table);
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
            'index' => ListInterventionTypes::route('/'),
            'create' => CreateInterventionType::route('/create'),
            'edit' => EditInterventionType::route('/{record}/edit'),
        ];
    }
}
