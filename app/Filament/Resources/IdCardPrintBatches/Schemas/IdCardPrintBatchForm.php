<?php

namespace App\Filament\Resources\IdCardPrintBatches\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IdCardPrintBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch Identity')
                    ->description('General classification and naming for this print run.')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('batch_name')
                                ->label('Batch Reference Name')
                                ->placeholder('e.g., May 2026 Orphan Batch')
                                ->required()
                                ->maxLength(255),

                            Select::make('type')
                                ->options([
                                    'widow' => 'Widows',
                                    'orphan' => 'Orphans',
                                    'mixed' => 'Mixed',
                                ])
                                ->disabledOn('edit'),

                            Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'processing' => 'Processing',
                                    'completed' => 'Completed',
                                    'failed' => 'Failed',
                                ])
                                ->required()
                                ->default('pending')
                                ->native(false),
                        ]),
                    ]),

                Section::make('Selection Criteria')
                    ->description('The filters and sequences used to generate this batch.')
                    ->icon('heroicon-m-funnel')
                    ->schema([
                        KeyValue::make('filters')
                            ->label('Query Filters')
                            ->helperText('Specific database criteria used for selection.')
                            ->keyLabel('Field')
                            ->valueLabel('Value'),

                        Grid::make(2)->schema([
                            TextInput::make('range.from')
                                ->label('Start Sequence')
                                ->numeric(),
                            TextInput::make('range.to')
                                ->label('End Sequence')
                                ->numeric(),
                        ]),
                    ]),

                Section::make('Processing & Output')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('total_count')
                                ->label('Total Records')
                                ->numeric()
                                ->required()
                                ->minValue(1),

                            TextInput::make('processed_count')
                                ->label('Processed')
                                ->numeric()
                                ->default(0)
                                ->required(),

                            Placeholder::make('progress_percentage')
                                ->label('Job Progress')
                                ->content(fn($record) => $record ? $record->progressPercentage() . '%' : '0%'),
                        ]),

                        FileUpload::make('pdf_path')
                            ->label('Generated PDF Document')
                            ->directory('id-card-batches')
                            ->disk('public')
                            ->acceptedFileTypes(['application/pdf'])
                            ->openable()
                            ->downloadable(),
                    ]),

                Section::make('Timeline & Audit')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            DateTimePicker::make('started_at')
                                ->label('Processing Started')
                                ->native(false),
                            DateTimePicker::make('completed_at')
                                ->label('Processing Completed')
                                ->native(false),
                        ]),

                        Hidden::make('created_by')
                            ->default(auth()->id()),
                    ]),
            ]);
    }
}
