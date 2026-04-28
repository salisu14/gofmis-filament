<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

/* -----------------------------
 | 2. LOAN REPAYMENTS MANAGER
 ------------------------------*/

use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RepaymentsRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoan::class;
    protected static string $relationship = 'repayments';
    protected static ?string $title = 'Actual Repayments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('amount')
                    ->numeric()
                    ->prefix('₦')
                    ->required(),
                DatePicker::make('paid_at')
                    ->default(now())
                    ->required()
                    ->native(false),
                Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Bank Transfer',
                        'deduction' => 'Monthly Deduction',
                    ])
                    ->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')->date()->sortable(),
                TextColumn::make('amount')->money('NGN')->summarize(Sum::make()->money('NGN')),
                TextColumn::make('payment_method')->badge()->color('gray'),
                TextColumn::make('transaction.reference')->label('Ref')->placeholder('No Transaction'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record Repayment')
                    ->icon('heroicon-m-banknotes')
                    ->modalWidth('xl'),
            ])
            ->recordActions([
                Action::make('printReceipt')
                    ->label('Receipt')
                    ->icon('heroicon-m-printer')
                    ->color('info')
                    ->modalHeading('Repayment Receipt')
                    ->modalContent(fn(WidowLoanRepayment $record) => view('components.loan-receipt', [
                        'record' => $record,
                        'widow' => $record->widowLoan->widow,
                        'balance' => $record->widowLoan->total_payable - $record->widowLoan->repayments()->where('paid_at', '<=', $record->paid_at)->sum('amount'),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
