<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

/* -----------------------------
 | 1. LOAN SCHEDULES MANAGER
 ------------------------------*/

use App\Models\WidowLoan;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchedulesRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoan::class;
    protected static string $relationship = 'schedules';
    protected static ?string $title = 'Repayment Schedule';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('installment_number')->numeric()->required(),
            TextInput::make('amount_due')->numeric()->prefix('₦')->required(),
            DatePicker::make('due_date')->required()->native(false),
            Toggle::make('is_paid')->label('Paid Status'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('installment_number')->label('#')->alignCenter(),
                TextColumn::make('due_date')->date()->sortable(),
                TextColumn::make('amount_due')->money('NGN'),
                IconColumn::make('is_paid')->label('Status')->boolean(),
            ])
            ->defaultSort('due_date', 'asc')
            ->headerActions([
                // Schedule creation is handled automatically by WidowLoanService::disburseLoan().
                // Manual creation is intentionally removed to keep the schedule authoritative.
            ])
            ->recordActions([
                // Only super admins can manually correct schedule entries.
                EditAction::make()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                DeleteAction::make()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ]);
    }
}
