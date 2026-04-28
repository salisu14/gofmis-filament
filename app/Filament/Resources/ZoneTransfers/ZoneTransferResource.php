<?php

namespace App\Filament\Resources\ZoneTransfers;

use App\Filament\Resources\ZoneTransfers\Pages\CreateZoneTransfer;
use App\Filament\Resources\ZoneTransfers\Pages\EditZoneTransfer;
use App\Filament\Resources\ZoneTransfers\Pages\ListZoneTransfers;
use App\Filament\Resources\ZoneTransfers\Schemas\ZoneTransferForm;
use App\Filament\Resources\ZoneTransfers\Tables\ZoneTransfersTable;
use App\Models\ZoneTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZoneTransferResource extends Resource
{
    protected static ?string $model = ZoneTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ZoneTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZoneTransfersTable::configure($table);
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
            'index' => ListZoneTransfers::route('/'),
            'create' => CreateZoneTransfer::route('/create'),
            'edit' => EditZoneTransfer::route('/{record}/edit'),
        ];
    }
}
