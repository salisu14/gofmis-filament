<?php

namespace App\Filament\Resources\Verifications\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EducationVerificationForm
{
    /**
     * Form used for the Verification/Edit process.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Request Reference')
                    ->description('Contextual details for the original coordinator request.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('orphan_name')
                            ->label('Beneficiary Name')
                            ->formatStateUsing(fn($record) => $record?->orphan?->full_name)
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('requested_amount')
                            ->label('Requested Funds')
                            ->prefix('₦')
                            ->formatStateUsing(fn($state) => number_format($state, 2))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('type_name')
                            ->label('Intervention Type')
                            ->formatStateUsing(fn($record) => $record?->type?->name)
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('request_date')
                            ->label('Submission Date')
                            ->formatStateUsing(fn($state) => $state ? $state->format('d M, Y') : null)
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Verification Decision')
                    ->icon('heroicon-m-check-badge')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Final Decision')
                            ->options([
                                'pending' => 'Pending',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('verification_status')
                            ->label('Verification Progress')
                            ->options([
                                'pending' => 'Pending',
                                'in_progress' => 'In Progress',
                                'verified' => 'Verified',
                                'failed' => 'Failed',
                            ])
                            ->required()
                            ->native(false),

                        Textarea::make('verification_notes')
                            ->label('Verification Audit Notes')
                            ->placeholder('e.g. Verified with school principal, receipt authenticity confirmed...')
                            ->rows(4)
                            ->columnSpanFull(),

                        FileUpload::make('verification_documents')
                            ->label('Supporting Evidence')
                            ->multiple()
                            ->directory('education-verifications')
                            ->disk('public')
                            ->visibility('public')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->columnSpanFull(),
                    ]),

                Hidden::make('verified_by')
                    ->default(auth()->id()),

                Hidden::make('verified_at')
                    ->default(now()),
            ]);
    }
}
