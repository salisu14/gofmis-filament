<?php

namespace App\Filament\Resources\OutOfPocketExpenditures\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OutOfPocketExpenditureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expenditure Details')
                    ->schema([
                        TextInput::make('reference')
                            ->label('Reference Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated (e.g. OOP-20260903-XXXXXX)'),

                        DatePicker::make('expenditure_date')
                            ->label('Expenditure Date')
                            ->required()
                            ->default(now()),

                        Select::make('incurred_by_user_id')
                            ->label('Incurred By (Staff / Officer)')
                            ->options(User::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(auth()->id()),

                        Select::make('category')
                            ->label('Expense Category')
                            ->options([
                                'transportation' => 'Transportation & Travel',
                                'office_supplies' => 'Office Supplies & Stationeries',
                                'emergency_welfare' => 'Emergency Beneficiary Welfare',
                                'medical' => 'Medical & Healthcare Expense',
                                'education_support' => 'Education Support',
                                'utilities' => 'Utilities & Logistics',
                                'other' => 'Other Operational Expense',
                            ])
                            ->required(),

                        TextInput::make('payee_name')
                            ->label('Payee / Vendor Name')
                            ->nullable(),

                        TextInput::make('amount')
                            ->label('Amount (NGN)')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->minValue(0.01),

                        Select::make('payment_method')
                            ->label('Personal Payment Source')
                            ->options([
                                'personal_cash' => 'Personal Cash',
                                'personal_transfer' => 'Personal Bank Transfer',
                                'personal_card' => 'Personal Debit/Credit Card',
                            ])
                            ->nullable(),

                        Textarea::make('description')
                            ->label('Purpose & Description')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Reimbursement & Attachments')
                    ->schema([
                        Toggle::make('reimbursement_required')
                            ->label('Reimbursement Required from Foundation Cash')
                            ->default(true),

                        FileUpload::make('receipt_path')
                            ->label('Supporting Receipt / Evidence')
                            ->disk('public')
                            ->directory('out-of-pocket-receipts')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Internal Notes')
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
