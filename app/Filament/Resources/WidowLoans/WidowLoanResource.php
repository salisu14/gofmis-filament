<?php

namespace App\Filament\Resources\WidowLoans;

use App\Filament\Resources\WidowLoans\Pages\CreateWidowLoan;
use App\Filament\Resources\WidowLoans\Pages\EditWidowLoan;
use App\Filament\Resources\WidowLoans\Pages\ListWidowLoans;
use App\Filament\Resources\WidowLoans\Pages\ViewWidowLoan;
use App\Filament\Resources\WidowLoans\Schemas\WidowLoanForm;
use App\Filament\Resources\WidowLoans\Schemas\WidowLoanInfolist;
use App\Filament\Resources\WidowLoans\Tables\WidowLoansTable;
use App\Models\WidowLoan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WidowLoanResource extends Resource
{
    protected static ?string $model = WidowLoan::class;

    protected static ?string $navigationLabel = 'Widow Loans';

    protected static ?string $modelLabel = 'Widow Loan';

    protected static ?string $pluralModelLabel = 'Widow Loans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|null|\UnitEnum $navigationGroup = 'Widow Services';

    public static function form(Schema $schema): Schema
    {
        return WidowLoanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WidowLoanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WidowLoansTable::configure($table);
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
            'index' => ListWidowLoans::route('/'),
            'create' => CreateWidowLoan::route('/create'),
            'view' => ViewWidowLoan::route('/{record}'),
            'edit' => EditWidowLoan::route('/{record}/edit'),
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
