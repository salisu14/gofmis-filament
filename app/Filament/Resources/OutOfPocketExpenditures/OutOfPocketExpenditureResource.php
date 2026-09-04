<?php

namespace App\Filament\Resources\OutOfPocketExpenditures;

use App\Filament\Resources\OutOfPocketExpenditures\Pages\CreateOutOfPocketExpenditure;
use App\Filament\Resources\OutOfPocketExpenditures\Pages\EditOutOfPocketExpenditure;
use App\Filament\Resources\OutOfPocketExpenditures\Pages\ListOutOfPocketExpenditures;
use App\Filament\Resources\OutOfPocketExpenditures\Pages\ViewOutOfPocketExpenditure;
use App\Filament\Resources\OutOfPocketExpenditures\Schemas\OutOfPocketExpenditureForm;
use App\Filament\Resources\OutOfPocketExpenditures\Tables\OutOfPocketExpendituresTable;
use App\Models\OutOfPocketExpenditure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OutOfPocketExpenditureResource extends Resource
{
    protected static ?string $model = OutOfPocketExpenditure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|null|\UnitEnum $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Out of Pocket Expenditures';

    protected static ?string $slug = 'out-of-pocket-expenditures';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('coordinator')) {
            return false;
        }

        return $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->can('out_of_pocket_expenditure.view')
            || (method_exists($user, 'isDemoObserver') && $user->isDemoObserver());
    }

    public static function form(Schema $schema): Schema
    {
        return OutOfPocketExpenditureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutOfPocketExpendituresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutOfPocketExpenditures::route('/'),
            'create' => CreateOutOfPocketExpenditure::route('/create'),
            'view' => ViewOutOfPocketExpenditure::route('/{record}'),
            'edit' => EditOutOfPocketExpenditure::route('/{record}/edit'),
        ];
    }
}
