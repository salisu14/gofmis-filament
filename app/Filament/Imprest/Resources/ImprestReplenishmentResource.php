<?php

namespace App\Filament\Imprest\Resources;

use App\Filament\Imprest\Resources\ImprestReplenishmentResource\Pages;
use App\Models\ImprestReplenishment;
use App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
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

class ImprestReplenishmentResource extends Resource
{
    protected static ?string $model = ImprestReplenishment::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-arrow-path';
    protected static string|null|\UnitEnum $navigationGroup = 'Fund Management';
    protected static ?int $navigationSort = 3;


    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['fund', 'requester', 'approver']);

        $user = auth()->user();
        $canManageAll = $user?->hasAnyRole(['admin', 'super_admin']) || $user?->hasPermissionTo('imprest.manage_all');

        if (! $canManageAll) {
            $query->where(function ($query) {
                $query
                    ->where('requested_by', auth()->id())
                    ->orWhereHas('fund', fn ($fundQuery) => $fundQuery->where('custodian_id', auth()->id()));
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Replenishment Request')
                    ->columns(2)
                    ->schema([
                        Select::make('fund_id')
                            ->relationship(
                                name: 'fund',
                                titleAttribute: 'location',
                                modifyQueryUsing: fn ($query) => $query->where('status', 'active')
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $fundId = $get('fund_id');
                                if (!$fundId) return;

                                $fund = \App\Models\ImprestFund::find($fundId);
                                if ($fund) {
                                    $spent = $fund->authorized_amount - $fund->current_balance;
                                    $set('amount', $spent);
                                    $set('receipts_total', $spent);
                                }
                            }),

                        DatePicker::make('period_start')
                            ->required()
                            ->default(now()->startOfMonth())
                            ->native(false)
                            ->live(),

                        DatePicker::make('period_end')
                            ->required()
                            ->default(now()->endOfMonth())
                            ->native(false)
                            ->after('period_start'),

                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-calculated from fund activity'),

                        TextInput::make('receipts_total')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('variance')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),

                        Hidden::make('requested_by')
                            ->default(auth()->id()),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Any additional information for the approver'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fund.location')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right')
                    ->weight('font-bold'),

                Tables\Columns\TextColumn::make('receipts_total')
                    ->money('NGN')
                    ->alignment('right')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('variance')
                    ->money('NGN')
                    ->alignment('right')
                    ->color(fn(float $state): string => $state != 0 ? 'danger' : 'success')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'approved' => 'primary',
                        'rejected' => 'danger',
                        'processed' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'processed' => 'Processed',
                    ])
                    ->multiple()
                    ->native(false),

                Tables\Filters\SelectFilter::make('fund_id')
                    ->relationship('fund', 'location')
                    ->searchable()
                    ->preload()
                    ->label('Fund'),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(ImprestReplenishment $record): bool => $record->status === 'submitted' && auth()->user()->can('approve', $record->fund)
                    )
                    ->action(function (ImprestReplenishment $record) {
                        try {
                            $service = app(ImprestReplenishmentServiceInterface::class);
                            $service->approve($record->id, auth()->id());

                            Notification::make()
                                ->title('Replenishment Approved')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Approval failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('process')
                    ->icon('heroicon-m-play')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Process Replenishment')
                    ->modalDescription(fn (ImprestReplenishment $record): string => $record->fund?->bank_account_id
                        ? 'This will restore the fund to its authorized amount.'
                        : 'This fund is not linked to a bank account yet, so payment cannot be processed.')
                    ->visible(fn(ImprestReplenishment $record): bool => $record->status === 'approved' && auth()->user()->can('replenish', $record->fund)
                    )
                    ->disabled(fn (ImprestReplenishment $record): bool => blank($record->fund?->bank_account_id))
                    ->action(function (ImprestReplenishment $record) {
                        try {
                            $service = app(ImprestReplenishmentServiceInterface::class);
                            $service->process($record->id);

                            Notification::make()
                                ->title('Replenishment Processed')
                                ->success()
                                ->body('Fund balance has been restored.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Processing failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->visible(fn(ImprestReplenishment $record): bool => $record->status === 'draft'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Replenishment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('fund.location')
                            ->label('Fund'),
                        TextEntry::make('fund.status')
                            ->label('Fund Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'active' => 'success',
                                'suspended' => 'warning',
                                'closed' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'submitted' => 'warning',
                                'approved' => 'primary',
                                'rejected' => 'danger',
                                'processed' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('period_start')
                            ->date(),
                        TextEntry::make('period_end')
                            ->date(),
                        TextEntry::make('processed_at')
                            ->dateTime()
                            ->placeholder('Not processed'),
                    ]),

                Section::make('Financials')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('amount')
                            ->money('NGN'),
                        TextEntry::make('receipts_total')
                            ->money('NGN'),
                        TextEntry::make('variance')
                            ->money('NGN')
                            ->color(fn ($state): string => (float) $state === 0.0 ? 'success' : 'danger'),
                        TextEntry::make('fund.bankAccount.account_name')
                            ->label('Funding Bank')
                            ->placeholder('No bank account linked'),
                    ]),

                Section::make('People & Notes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('requester.name')
                            ->label('Requested By')
                            ->placeholder('N/A'),
                        TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->placeholder('Not approved'),
                        TextEntry::make('notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImprestReplenishments::route('/'),
            'create' => Pages\CreateImprestReplenishment::route('/create'),
            'view' => Pages\ViewImprestReplenishment::route('/{record}'),
            'edit' => Pages\EditImprestReplenishment::route('/{record}/edit'),
        ];
    }
}
