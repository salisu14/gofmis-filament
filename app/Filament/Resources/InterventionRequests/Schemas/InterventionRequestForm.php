<?php

namespace App\Filament\Resources\InterventionRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InterventionRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Context')
                    ->description('General details about the intervention requested.')
                    ->icon('heroicon-m-document-text')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('orphan_id')
                                ->label('Orphan')
                                ->relationship('orphan', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('intervention_type_id')
                                ->label('Request Type')
                                ->relationship('type', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            DatePicker::make('request_date')
                                ->default(now())
                                ->required(),

                            Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'under_review' => 'Under Review',
                                    'approved' => 'Approved',
                                    'partially_fulfilled' => 'Partially Fulfilled',
                                    'fulfilled' => 'Fulfilled',
                                    'rejected' => 'Rejected',
                                ])
                                ->required()
                                ->default('pending')
                                ->disabled()
                                ->dehydrated(),
                        ]),
                    ]),

                Section::make('Verification')
                    ->description('Status of administrative verification.')
                    ->collapsed()
                    ->schema([
                        Select::make('verification_status')
                            ->options([
                                'pending' => 'Pending',
                                'in_progress' => 'In Progress',
                                'verified' => 'Verified',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->disabled()
                            ->dehydrated(),

                        Select::make('verified_by')
                            ->relationship('verifier', 'name')
                            ->searchable(),

                        DateTimePicker::make('verified_at'),

                        Textarea::make('rejection_reason')
                            ->label('Rejection/Flag Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }
}
