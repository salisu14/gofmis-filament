<?php

namespace App\Filament\Resources\IdCards;

use App\Filament\Resources\IdCards\Pages\CreateIdCard;
use App\Filament\Resources\IdCards\Pages\EditIdCard;
use App\Filament\Resources\IdCards\Pages\ListIdCards;
use App\Filament\Resources\IdCards\Pages\ViewIdCard;
use App\Filament\Resources\IdCards\Schemas\IdCardForm;
use App\Filament\Resources\IdCards\Schemas\IdCardInfolist;
use App\Filament\Resources\IdCards\Tables\IdCardsTable;
use App\Models\IdCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IdCardResource extends Resource
{
    protected static ?string $model = IdCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Identification;
    protected static string|null|\UnitEnum $navigationGroup = 'ID Card Management';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return IdCardForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IdCardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IdCardsTable::configure($table);
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
            'index' => ListIdCards::route('/'),
            'create' => CreateIdCard::route('/create'),
            'bulk-print' => Pages\BulkPrintIdCards::route('/bulk-print'),
            'view' => ViewIdCard::route('/{record}'),
            'edit' => EditIdCard::route('/{record}/edit'),
            'preview' => Pages\PreviewIdCard::route('/{record}/preview'),
        ];
    }
}
