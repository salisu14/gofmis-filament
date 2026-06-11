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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'type'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->withCount('idCards');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Type' => ucfirst($record->type),
            'Active' => $record->is_active ? 'Yes' : 'No',
            'Cards' => number_format((int) ($record->id_cards_count ?? 0)),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('edit', ['record' => $record]);
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
