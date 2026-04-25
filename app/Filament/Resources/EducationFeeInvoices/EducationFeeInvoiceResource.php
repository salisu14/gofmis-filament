<?php

namespace App\Filament\Resources\EducationFeeInvoices;

use App\Filament\Resources\EducationFeeInvoices\Pages\CreateEducationFeeInvoice;
use App\Filament\Resources\EducationFeeInvoices\Pages\EditEducationFeeInvoice;
use App\Filament\Resources\EducationFeeInvoices\Pages\ListEducationFeeInvoices;
use App\Filament\Resources\EducationFeeInvoices\Schemas\EducationFeeInvoiceForm;
use App\Filament\Resources\EducationFeeInvoices\Tables\EducationFeeInvoicesTable;
use App\Models\EducationFeeInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EducationFeeInvoiceResource extends Resource
{
    protected static ?string $model = EducationFeeInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EducationFeeInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EducationFeeInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducationFeeInvoices::route('/'),
            'create' => CreateEducationFeeInvoice::route('/create'),
            'edit' => EditEducationFeeInvoice::route('/{record}/edit'),
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
