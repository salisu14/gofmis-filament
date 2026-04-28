<?php

namespace App\Filament\Resources\IdCardPrintBatches;

use App\Filament\Resources\IdCardPrintBatches\Pages\CreateIdCardPrintBatch;
use App\Filament\Resources\IdCardPrintBatches\Pages\EditIdCardPrintBatch;
use App\Filament\Resources\IdCardPrintBatches\Pages\ListIdCardPrintBatches;
use App\Filament\Resources\IdCardPrintBatches\Schemas\IdCardPrintBatchForm;
use App\Filament\Resources\IdCardPrintBatches\Schemas\IdCardPrintBatchInfolist;
use App\Filament\Resources\IdCardPrintBatches\Tables\IdCardPrintBatchesTable;
use App\Models\IdCardPrintBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IdCardPrintBatchResource extends Resource
{
    protected static ?string $model = IdCardPrintBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static string|null|\UnitEnum $navigationGroup = 'ID Card Management';

    protected static ?string $label = 'Print Batch';
    protected static ?string $pluralLabel = 'Print Batches';
    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'batch_name';

    public static function form(Schema $schema): Schema
    {
        return IdCardPrintBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IdCardPrintBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IdCardPrintBatchesTable::configure($table);
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
            'index' => ListIdCardPrintBatches::route('/'),
            'create' => CreateIdCardPrintBatch::route('/create'),
            'edit' => EditIdCardPrintBatch::route('/{record}/edit'),
        ];
    }
}
