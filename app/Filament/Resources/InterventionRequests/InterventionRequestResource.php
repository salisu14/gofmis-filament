<?php

namespace App\Filament\Resources\InterventionRequests;

use App\Filament\Resources\InterventionRequests\Pages\CreateInterventionRequest;
use App\Filament\Resources\InterventionRequests\Pages\EditInterventionRequest;
use App\Filament\Resources\InterventionRequests\Pages\ListInterventionRequests;
use App\Filament\Resources\InterventionRequests\Schemas\InterventionRequestForm;
use App\Filament\Resources\InterventionRequests\Tables\InterventionRequestsTable;
use App\Models\InterventionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InterventionRequestResource extends Resource
{
    protected static ?string $model = InterventionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return InterventionRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InterventionRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\InterventionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterventionRequests::route('/'),
            'create' => CreateInterventionRequest::route('/create'),
            'edit' => EditInterventionRequest::route('/{record}/edit'),
        ];
    }
}
