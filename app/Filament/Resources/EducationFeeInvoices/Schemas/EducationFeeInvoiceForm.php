<?php

namespace App\Filament\Resources\EducationFeeInvoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EducationFeeInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Overview')
                    ->schema([
                        TextInput::make('reference')
                            ->label('Invoice Ref')
                            ->placeholder('Generated automatically')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('orphan_education_id')
                            ->label('Education Record')
                            ->relationship('education', 'id')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->orphan->full_name} — {$record->institution->name} ({$record->level})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (! $state) {
                                    return;
                                }

                                $education = \App\Models\OrphanEducation::find($state);
                                if ($education && blank($education->school_fee) === false) {
                                    $set('amount', $education->school_fee);
                                }
                            }),

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
                            ->default('pending')
                            ->helperText('Payment status is recalculated automatically from payment history. Use Cancelled only to void an unpaid invoice.')
                            ->disabled(fn (string $operation, Get $get): bool => $operation === 'create' || in_array($get('status'), ['partial', 'paid'], true))
                            ->dehydrated(),
                    ])->columns(3),
            ]);
    }
}
