<?php

namespace App\Filament\Resources\InterventionRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InterventionRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Details')
                    ->description('Core details about the intervention needed.')
                    ->icon('heroicon-m-document-text')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('orphan_id')
                                ->label('Orphan / Beneficiary')
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

                        Grid::make(3)->schema([
                            DatePicker::make('request_date')
                                ->label('Request Date')
                                ->default(now())
                                ->required(),

                            TextInput::make('requested_level')
                                ->label('Requested Level / Class')
                                ->maxLength(255)
                                ->placeholder('e.g., SS2, Primary 5, University'),

                            TextInput::make('requested_amount')
                                ->label('Estimated Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->step(0.01)
                                ->minValue(0),
                        ]),

                        Textarea::make('notes')
                            ->label('Request Justification / Notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Explain why this intervention is needed...'),

                        FileUpload::make('supporting_documents')
                            ->label('Supporting Documents')
                            ->multiple()
                            ->directory('intervention-requests')
                            ->disk('public')
                            ->visibility('private') // Keep sensitive documents private
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->columnSpanFull(),

                        Select::make('status')
                            ->label('Current Status')
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
                            ->dehydrated()
                            ->columnSpanFull(),
                    ]),

                Section::make('Verification Details')
                    ->description('Administrative verification audit trail.')
                    ->icon('heroicon-m-shield-check')
                    ->collapsed()
                    ->hiddenOn('create') // Hide when initially creating the request
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('verification_status')
                                ->label('Verification Status')
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
                                ->label('Verified By')
                                ->relationship('verifier', 'name')
                                ->searchable()
                                ->preload()
                                ->disabled(),

                            DateTimePicker::make('verified_at')
                                ->label('Verified At')
                                ->disabled(),
                        ]),

                        DatePicker::make('verification_date')
                            ->label('Verification Date')
                            ->disabled(),

                        Textarea::make('verification_notes')
                            ->label('Verification Notes')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled(),

                        FileUpload::make('verification_documents')
                            ->label('Verification Evidence')
                            ->multiple()
                            ->directory('intervention-verifications')
                            ->disk('public')
                            ->visibility('private')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Section::make('Review & Approval')
                    ->description('Final review and approval audit trail.')
                    ->icon('heroicon-m-check-circle')
                    ->collapsed()
                    ->hiddenOn('create') // Hide when initially creating the request
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('reviewed_by')
                                ->label('Reviewed By')
                                ->relationship('reviewer', 'name')
                                ->searchable()
                                ->preload()
                                ->disabled(),

                            DateTimePicker::make('reviewed_at')
                                ->label('Reviewed At')
                                ->disabled(),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('approved_by')
                                ->label('Approved By')
                                ->relationship('approver', 'name')
                                ->searchable()
                                ->preload()
                                ->disabled(),

                            DateTimePicker::make('approved_at')
                                ->label('Approved At')
                                ->disabled(),
                        ]),

                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled()
                            ->visible(fn ($get) => $get('status') === 'rejected'),
                    ]),
            ]);
    }
}
