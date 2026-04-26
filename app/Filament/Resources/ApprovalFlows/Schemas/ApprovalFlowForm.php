<?php

namespace App\Filament\Resources\ApprovalFlows\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApprovalFlowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Flow Context')
                    ->description('Details regarding the entity currently under review.')
                    ->icon('heroicon-m-arrows-right-left')
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('model_type')
                                ->label('Approvable Type')
                                ->content(fn ($record) => $record ? str($record->model_type)->afterLast('\\')->headline() : '-'),

                            Placeholder::make('approvable_id')
                                ->label('Entity Reference')
                                ->content(fn ($record) => $record->model_id ?? '-'),

                            Placeholder::make('progress')
                                ->label('Overall Progress')
                                ->content(fn ($record) => $record ? "Step {$record->current_step} of {$record->total_steps}" : '-'),
                        ]),
                    ]),

                Section::make('Status & Final Result')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'approved' => 'Fully Approved',
                                    'rejected' => 'Rejected',
                                ])
                                ->required()
                                ->native(false),

                            DateTimePicker::make('approved_at')
                                ->label('Approval Finalized')
                                ->disabled()
                                ->dehydrated(false),

                            DateTimePicker::make('rejected_at')
                                ->label('Rejection Finalized')
                                ->disabled()
                                ->dehydrated(false),
                        ]),

                        Textarea::make('rejection_reason')
                            ->label('Final Rejection Comments')
                            ->placeholder('Provide a reason if the flow is terminated...')
                            ->visible(fn ($get) => $get('status') === 'rejected')
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval Steps')
                    ->description('Chronological sequence of approvals required.')
                    ->icon('heroicon-m-list-bullet')
                    ->schema([
                        Repeater::make('steps')
                            ->relationship('steps')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextInput::make('step_number')
                                        ->label('Order')
                                        ->numeric()
                                        ->disabled(),

                                    TextInput::make('role_name')
                                        ->label('Required Role')
                                        ->disabled(),

                                    Select::make('status')
                                        ->options([
                                            'pending' => 'Pending',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                        ])
                                        ->disabled(),

                                    DateTimePicker::make('completed_at')
                                        ->label('Processed At')
                                        ->disabled(),
                                ]),

                                Textarea::make('comments')
                                    ->label('Approver Comments')
                                    ->rows(1)
                                    ->disabled()
                                    ->columnSpanFull(),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => "Step " . ($state['step_number'] ?? '') . ": " . ($state['role_name'] ?? ''))
                            ->collapsible()
                    ]),
            ]);
    }
}
