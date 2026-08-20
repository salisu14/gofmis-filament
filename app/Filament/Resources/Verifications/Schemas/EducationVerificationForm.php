<?php

namespace App\Filament\Resources\Verifications\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
                            ->formatStateUsing(fn ($record) => $record?->orphan?->full_name)
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('requested_amount')
                            ->label('Requested Funds')
                            ->prefix('₦')
                            ->formatStateUsing(fn ($state) => number_format($state, 2))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('type_name')
                            ->label('Intervention Type')
                            ->formatStateUsing(fn ($record) => $record?->type?->name)
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('request_date')
                            ->label('Submission Date')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d M, Y') : null) // ✅ FIX APPLIED HERE
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Submitted Requested Items')
                    ->description('Physical items requested by the coordinator.')
                    ->icon('heroicon-m-shopping-bag')
                    ->visible(fn ($record) => $record?->items()->exists())
                    ->schema([
                        Placeholder::make('requested_items_summary')
                            ->label('Requested Items List')
                            ->content(function ($record) {
                                if (! $record || $record->items->isEmpty()) {
                                    return 'No physical items requested.';
                                }

                                return $record->items->map(function ($item) {
                                    $name = $item->item_name ?: ($item->item?->name ?? 'Item');
                                    $qty = $item->quantity_requested;
                                    $context = $item->orphan_class ? " (Size/Context: {$item->orphan_class})" : '';
                                    $specs = $item->specification ? " - Details: {$item->specification}" : '';
                                    $fulfilled = " | Fulfilled: {$item->quantity_fulfilled}/{$item->quantity_requested}";

                                    return "• {$name} x{$qty}{$context}{$specs}{$fulfilled}";
                                })->implode("\n");
                            }),
                    ]),

                Section::make('Verification & Decision Status')
                    ->icon('heroicon-m-check-badge')
                    ->columns(2)
                    ->schema([
                        TextInput::make('status')
                            ->label('Final Decision')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'under_review' => 'Under Review',
                                default => 'Pending',
                            })
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('verification_status')
                            ->label('Verification Progress')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'verified' => 'Verified',
                                'failed' => 'Failed',
                                'in_progress' => 'In Progress',
                                default => 'Pending',
                            })
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('verification_notes')
                            ->label('Verification Audit Notes')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('verification_documents')
                            ->label('Supporting Evidence')
                            ->disabled()
                            ->dehydrated(false)
                            ->multiple()
                            ->disk('public')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),

                Hidden::make('verified_by')
                    ->default(auth()->id()),

                Hidden::make('verified_at')
                    ->default(now()),
            ]);
    }
}
