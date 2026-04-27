<?php

namespace App\Filament\Resources\Sponsorships\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SponsorshipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sponsor & Beneficiary')
                    ->description('Identify the donor and the orphan receiving support.')
                    ->icon('heroicon-m-heart')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('orphan_id')
                                ->label('Sponsored Orphan')
                                ->relationship('orphan', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->hint('Select the student receiving this support.'),

                            Select::make('sponsor_id')
                                ->label('Sponsor')
                                ->relationship('sponsor', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                    Select::make('type')
                                        ->options(\App\Enums\SponsorType::class)
                                        ->required(),
                                    TextInput::make('email')
                                        ->email()
                                        ->maxLength(255),
                                    TextInput::make('phone')
                                        ->tel()
                                        ->maxLength(255),
                                ])
                                ->hint('Select or create a new sponsor.'),
                        ]),
                    ]),

                Section::make('Financial Commitment')
                    ->description('Specify the amount and duration of the sponsorship.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('amount_committed')
                                ->label('Total Commitment')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->helperText('The full amount pledged for the specified duration.'),

                            DatePicker::make('start_date')
                                ->label('Effective Date')
                                ->default(now())
                                ->required()
                                ->native(false),

                            DatePicker::make('end_date')
                                ->label('Expiry Date')
                                ->placeholder('Ongoing if empty')
                                ->native(false),
                        ]),

                        Textarea::make('notes')
                            ->label('Terms & Conditions')
                            ->placeholder('Enter any specific conditions or remarks for this sponsorship...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
