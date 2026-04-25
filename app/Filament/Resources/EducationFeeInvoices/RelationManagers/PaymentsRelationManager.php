<?php

namespace App\Filament\Resources\EducationFeeInvoices\RelationManagers;

use App\Filament\Resources\EducationFeeInvoices\EducationFeeInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $relatedResource = EducationFeeInvoiceResource::class;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $title = 'Payment History';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Payment Details')
                    ->schema([
                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->helperText('Enter the specific amount received for this transaction.'),

                        DatePicker::make('payment_date')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_deposit' => 'Bank Deposit',
                                'transfer' => 'Bank Transfer',
                                'pos' => 'POS',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('reference')
                            ->label('Transaction Reference')
                            ->placeholder('e.g. Teller No or Bank Ref')
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->summarize(Sum::make()->money('NGN')->label('Total Paid')),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('reference')
                    ->label('Ref')
                    ->searchable()
                    ->placeholder('No Ref'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Record Payment')
                    ->icon('heroicon-m-banknotes')
                    ->modalHeading('New Payment Entry')
                    ->modalWidth('xl'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
