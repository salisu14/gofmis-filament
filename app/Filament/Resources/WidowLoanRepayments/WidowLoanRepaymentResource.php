<?php

namespace App\Filament\Resources\WidowLoanRepayments;

use App\Filament\Resources\WidowLoanRepayments\Pages\CreateWidowLoanRepayment;
use App\Filament\Resources\WidowLoanRepayments\Pages\EditWidowLoanRepayment;
use App\Filament\Resources\WidowLoanRepayments\Pages\ListWidowLoanRepayments;
use App\Filament\Resources\WidowLoanRepayments\Schemas\WidowLoanRepaymentForm;
use App\Filament\Resources\WidowLoanRepayments\Tables\WidowLoanRepaymentsTable;
use App\Models\WidowLoanRepayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WidowLoanRepaymentResource extends Resource
{
    protected static ?string $model = WidowLoanRepayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return WidowLoanRepaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WidowLoanRepaymentsTable::configure($table);
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
            'index' => ListWidowLoanRepayments::route('/'),
            'create' => CreateWidowLoanRepayment::route('/create'),
            'edit' => EditWidowLoanRepayment::route('/{record}/edit'),
        ];
    }
}
