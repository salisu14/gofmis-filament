<?php

namespace App\Filament\Resources\IdCardTemplates;

use App\Filament\Resources\IdCardTemplates\Pages\CreateIdCardTemplate;
use App\Filament\Resources\IdCardTemplates\Pages\EditIdCardTemplate;
use App\Filament\Resources\IdCardTemplates\Pages\ListIdCardTemplates;
use App\Filament\Resources\IdCardTemplates\Schemas\IdCardTemplateForm;
use App\Filament\Resources\IdCardTemplates\Tables\IdCardTemplatesTable;
use App\Models\IdCardTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IdCardTemplateResource extends Resource
{
    protected static ?string $model = IdCardTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return IdCardTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IdCardTemplatesTable::configure($table);
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
            'index' => ListIdCardTemplates::route('/'),
            'create' => CreateIdCardTemplate::route('/create'),
            'edit' => EditIdCardTemplate::route('/{record}/edit'),
        ];
    }
}
