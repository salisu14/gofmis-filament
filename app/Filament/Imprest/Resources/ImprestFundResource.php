<?php

namespace App\Filament\Imprest\Resources;

use App\Filament\Imprest\Resources\ImprestFundResource\Pages\CreateImprestFund;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\EditImprestFund;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\ListImprestFunds;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\ViewImprestFund;
use App\Filament\Imprest\Resources\ImprestFundResource\RelationManagers\ReconciliationsRelationManager;
use App\Filament\Imprest\Resources\ImprestFundResource\RelationManagers\TransactionsRelationManager;
use App\Models\ImprestFund;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImprestFundResource extends Resource
{
    protected static ?string $model = ImprestFund::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-building-library';
    protected static string|null|\UnitEnum $navigationGroup = 'Fund Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'location';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['location', 'custodian.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Custodian' => $record->custodian?->name,
            'Balance' => '₦' . number_format($record->current_balance, 2),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Fund Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('custodian_id')
                            ->relationship('custodian', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('bank_account_id')
                            ->label('Funding Bank Account')
                            ->relationship('bankAccount', 'account_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_name} ({$record->account_number})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabledOn('edit'),

                        Forms\Components\TextInput::make('location')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Main Office, Branch A'),

                        Forms\Components\TextInput::make('authorized_amount')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->maxValue(999999.99)
                            ->minValue(0.01)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('current_balance', $state);
                            }),

                        Forms\Components\TextInput::make('current_balance')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'closed' => 'Closed',
                            ])
                            ->required()
                            ->native(false)
                            ->default('active'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold'),

                Tables\Columns\TextColumn::make('custodian.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bankAccount.account_name')
                    ->label('Bank')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('authorized_amount')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right')
                    ->color(fn(ImprestFund $record): string => $record->isLowBalance() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('utilization')
                    ->label('Used %')
                    ->state(fn(ImprestFund $record): float => $record->authorized_amount > 0
                        ? round((($record->authorized_amount - $record->current_balance) / $record->authorized_amount) * 100, 1)
                        : 0)
                    ->suffix('%')
                    ->color(fn(float $state): string => $state > 80 ? 'danger' : ($state > 50 ? 'warning' : 'success'))
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('last_reconciled_at')
                    ->label('Last Reconciled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'closed' => 'Closed',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('low_balance')
                    ->label('Low Balance')
                    ->placeholder('All funds')
                    ->trueLabel('Below 20%')
                    ->falseLabel('Above 20%')
                    ->queries(
                        true: fn($query) => $query->whereRaw('current_balance < (authorized_amount * 0.2)'),
                        false: fn($query) => $query->whereRaw('current_balance >= (authorized_amount * 0.2)'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('reconcile')
                    ->icon('heroicon-m-scale')
                    ->color('warning')
                    ->url(fn(ImprestFund $record): string => ImprestReconciliationResource::getUrl('create', ['fund_id' => $record->id]))
                    ->visible(fn(ImprestFund $record): bool => auth()->user()->can('reconcile', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Fund Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('location')
                            ->icon('heroicon-m-map-pin'),
                        TextEntry::make('custodian.name')
                            ->label('Custodian')
                            ->icon('heroicon-m-user'),
                        TextEntry::make('bankAccount.account_name')
                            ->label('Funding Bank')
                            ->placeholder('Not linked')
                            ->icon('heroicon-m-building-library'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'active' => 'success',
                                'suspended' => 'warning',
                                'closed' => 'danger',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Financial Summary')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('authorized_amount')
                            ->money('NGN')
                            ->icon('heroicon-m-banknotes'),
                        TextEntry::make('current_balance')
                            ->money('NGN')
                            ->color(fn(ImprestFund $record): string => $record->isLowBalance() ? 'danger' : 'success'),
                        TextEntry::make('total_spent')
                            ->state(fn(ImprestFund $record): float => $record->authorized_amount - $record->current_balance)
                            ->money('NGN'),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('last_reconciled_at')
                            ->dateTime()
                            ->placeholder('Never reconciled'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
            ReconciliationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImprestFunds::route('/'),
            'create' => CreateImprestFund::route('/create'),
            'view' => ViewImprestFund::route('/{record}'),
            'edit' => EditImprestFund::route('/{record}/edit'),
        ];
    }
}
