<?php

namespace App\Filament\Imprest\Resources;

use App\Filament\Imprest\Resources\ImprestReconciliationResource\Pages;
use App\Models\ImprestFund;
use App\Models\ImprestReconciliation;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ImprestReconciliationResource extends Resource
{
    protected static ?string $model = ImprestReconciliation::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-scale';
    protected static string|null|\UnitEnum $navigationGroup = 'Audit & Reconciliation';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Reconciliation Details')
                    ->columns(2)
                    ->schema([
                        Select::make('fund_id')
                            ->relationship('fund', 'location')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->default(fn () => request()->query('fund_id'))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $fundId = $get('fund_id');
                                if (!$fundId) return;

                                $fund = ImprestFund::find($fundId);
                                if ($fund) {
                                    // Calculate expected receipts total from active transactions
                                    $receiptsTotal = \App\Models\ImprestTransaction::where('fund_id', $fundId)
                                        ->where('status', 'active')
                                        ->sum('total_price');

                                    $set('expected_balance', $fund->authorized_amount);
                                    $set('receipts_total', $receiptsTotal);
                                    $set('cash_on_hand', $fund->current_balance);
                                }
                            }),

                        DatePicker::make('reconciliation_date')
                            ->required()
                            ->default(now())
                            ->native(false),

                        TextInput::make('cash_on_hand')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->step(0.01)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $cash = floatval($get('cash_on_hand') ?? 0);
                                $receipts = floatval($get('receipts_total') ?? 0);
                                $expected = floatval($get('expected_balance') ?? 0);
                                $set('actual_variance', round(($cash + $receipts) - $expected, 2));
                            }),

                        TextInput::make('receipts_total')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('expected_balance')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Authorized amount from fund setup'),

                        TextInput::make('actual_variance')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),
//                            ->color(fn (float $state): string => abs($state) < 0.01 ? 'success' : 'danger'),

                        Select::make('custodian_id')
                            ->relationship('custodian', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->label('Custodian Present'),
                    ]),

                Section::make('Discrepancy Resolution')
                    ->visible(fn (Get $get): bool => abs(floatval($get('actual_variance') ?? 0)) >= 0.01)
                    ->schema([
                        Textarea::make('variance_explanation')
                            ->required()
                            ->minLength(10)
                            ->placeholder('Explain any variance between expected and actual balances')
                            ->rows(3),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(2)
                            ->placeholder('Additional observations or comments'),
                    ]),

                Hidden::make('auditor_id')
                    ->default(auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fund.location')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reconciliation_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cash_on_hand')
                    ->money('NGN')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('receipts_total')
                    ->money('NGN')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('expected_balance')
                    ->money('NGN')
                    ->alignment('right')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('actual_variance')
                    ->money('NGN')
                    ->alignment('right')
                    ->color(fn (ImprestReconciliation $record): string => $record->isBalanced() ? 'success' : 'danger')
                    ->weight('font-bold'),

                Tables\Columns\IconColumn::make('is_balanced')
                    ->label('Balanced')
                    ->boolean()
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'flagged' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('auditor.name')
                    ->label('Auditor')
                    ->searchable(),

                Tables\Columns\IconColumn::make('custodian_acknowledged')
                    ->boolean()
                    ->label('Acknowledged'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'flagged' => 'Flagged',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('has_variance')
                    ->label('Variance Status')
                    ->placeholder('All')
                    ->trueLabel('Has Variance')
                    ->falseLabel('Balanced')
                    ->queries(
                        true: fn ($query) => $query->whereRaw('ABS(actual_variance) >= 0.01'),
                        false: fn ($query) => $query->whereRaw('ABS(actual_variance) < 0.01'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('acknowledge')
                    ->icon('heroicon-m-hand-thumb-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ImprestReconciliation $record): bool =>
                        !$record->custodian_acknowledged &&
                        auth()->id() === $record->custodian_id
                    )
                    ->action(function (ImprestReconciliation $record) {
                        $service = app(ImprestReconciliationServiceInterface::class);
                        $service->acknowledge($record->id, auth()->id());

                        Notification::make()
                            ->title('Reconciliation Acknowledged')
                            ->success()
                            ->send();
                    }),

                Action::make('complete')
                    ->icon('heroicon-m-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (ImprestReconciliation $record): bool =>
                        $record->status === 'in_progress' &&
                        auth()->user()->can('reconcile', $record->fund)
                    )
                    ->action(function (ImprestReconciliation $record) {
                        $record->update(['status' => 'completed']);

                        Notification::make()
                            ->title('Reconciliation Completed')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('reconciliation_date', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Reconciliation Summary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('fund.location')
                            ->label('Fund Location')
                            ->icon('heroicon-m-building-library'),
                        TextEntry::make('reconciliation_date')
                            ->date()
                            ->icon('heroicon-m-calendar'),
                    ]),

                Section::make('Balance Verification')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('cash_on_hand')
                            ->money('NGN')
                            ->icon('heroicon-m-banknotes'),
                        TextEntry::make('receipts_total')
                            ->money('NGN')
                            ->icon('heroicon-m-receipt-percent'),
                        TextEntry::make('expected_balance')
                            ->money('NGN')
                            ->icon('heroicon-m-scale'),
                        TextEntry::make('actual_variance')
                            ->money('NGN')
                            ->color(fn (ImprestReconciliation $record): string => $record->isBalanced() ? 'success' : 'danger')
                            ->weight('font-bold'),
                    ]),

                Section::make('Personnel')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('auditor.name')
                            ->label('Auditor')
                            ->icon('heroicon-m-user'),
                        TextEntry::make('custodian.name')
                            ->label('Custodian')
                            ->icon('heroicon-m-user'),
                        TextEntry::make('custodian_acknowledged')
                            ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                            ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('variance_explanation')
                            ->placeholder('No variance explanation provided')
                            ->markdown()
                            ->prose(),
                        TextEntry::make('notes')
                            ->placeholder('No additional notes')
                            ->markdown()
                            ->prose(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImprestReconciliations::route('/'),
            'create' => Pages\CreateImprestReconciliation::route('/create'),
            'view' => Pages\ViewImprestReconciliation::route('/{record}'),
            'edit' => Pages\EditImprestReconciliation::route('/{record}/edit'),
        ];
    }
}
