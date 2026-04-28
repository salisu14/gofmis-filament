<?php

namespace App\Filament\Resources\EducationFeeInvoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EducationFeeInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Overview')
                    ->schema([
                        Select::make('orphan_education_id')
                            ->label('Education Record')
                            ->relationship('education', 'id')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->orphan->full_name} — {$record->institution->name} ({$record->level})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        TextInput::make('period')
                            ->label('Billing Period')
                            ->placeholder('e.g. Term 1, 2026')
                            ->required(),
                    ])->columns(2),

                Section::make('Financials')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Total Invoice Amount')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->live(onBlur: true),

                        DatePicker::make('due_date')
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'partial' => 'Partially Paid',
                                'paid' => 'Fully Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->native(false)
                            ->default('pending'),
                    ])->columns(3),
            ]);
    }
}
