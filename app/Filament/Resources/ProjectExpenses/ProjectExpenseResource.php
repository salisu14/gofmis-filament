<?php

namespace App\Filament\Resources\ProjectExpenses;

use App\Filament\Resources\ProjectExpenses\Pages\CreateProjectExpense;
use App\Filament\Resources\ProjectExpenses\Pages\EditProjectExpense;
use App\Filament\Resources\ProjectExpenses\Pages\ListProjectExpenses;
use App\Filament\Resources\ProjectExpenses\Schemas\ProjectExpenseForm;
use App\Filament\Resources\ProjectExpenses\Tables\ProjectExpensesTable;
use App\Models\ProjectExpense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProjectExpenseResource extends Resource
{
    protected static ?string $model = ProjectExpense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ReceiptPercent;

    protected static string|null|\UnitEnum $navigationGroup = 'Projects & Interventions';
    protected static ?string $navigationLabel = 'Project Expenses';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProjectExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectExpensesTable::configure($table);
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
            'index' => ListProjectExpenses::route('/'),
            'create' => CreateProjectExpense::route('/create'),
            'edit' => EditProjectExpense::route('/{record}/edit'),
        ];
    }
}
