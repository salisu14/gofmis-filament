<?php

namespace App\Filament\Resources\WidowLoans\Schemas;

use App\Enums\WidowLoanStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Information')
                    ->description('Basic loan details')
                    ->collapsible()
                    ->schema([
                        Select::make('widow_id')
                            ->relationship('widow', 'full_name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('principal_amount')
                            ->label('Principal Amount')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0),
                        TextInput::make('duration_months')
                            ->label('Duration (Months)')
                            ->numeric()
                            ->minValue(1),
                        Textarea::make('purpose')
                            ->columnSpanFull(),
                        TextInput::make('loan_agreement_url')
                            ->label('Loan Agreement URL')
                            ->url(),
                    ]),

                Section::make('Loan Status & Amounts')
                    ->description('Tracking loan progress')
                    ->collapsible()
                    ->schema([
                        Select::make('status')
                            ->options(WidowLoanStatus::class)
                            ->required(),
                        TextInput::make('total_payable')
                            ->numeric()
                            ->step(0.01)
                            ->readOnly(),
                        TextInput::make('total_paid')
                            ->numeric()
                            ->step(0.01)
                            ->readOnly(),
                        TextInput::make('outstanding_balance')
                            ->numeric()
                            ->step(0.01)
                            ->readOnly(),
                        Toggle::make('fully_repaid')
                            ->label('Fully Repaid'),
                    ]),

                Section::make('Disbursement & Approval')
                    ->description('Disbursement details and approval tracking')
                    ->collapsible()
                    ->schema([
                        DateTimePicker::make('disbursed_at')
                            ->label('Disbursement Date'),
                        TextInput::make('approval_flow_id')
                            ->label('Approval Flow ID')
                            ->readOnly()
                            ->disabled(),
                        Textarea::make('reject_reason')
                            ->label('Rejection Reason')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
