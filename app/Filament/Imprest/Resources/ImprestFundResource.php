<?php

namespace App\Filament\Imprest\Resources;

use App\Enums\FundStatus;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\CreateImprestFund;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\EditImprestFund;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\ListImprestFunds;
use App\Filament\Imprest\Resources\ImprestFundResource\Pages\ViewImprestFund;
use App\Filament\Imprest\Resources\ImprestFundResource\RelationManagers\ReconciliationsRelationManager;
use App\Filament\Imprest\Resources\ImprestFundResource\RelationManagers\TransactionsRelationManager;
use App\Models\ImprestFund;
use App\Models\BankAccount;
use App\Models\Zone;
use App\Services\Imprest\ImprestFundStatusService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
                            ->relationship(
                                name: 'bankAccount',
                                titleAttribute: 'account_name',
                                modifyQueryUsing: fn ($query) => $query->dedicatedTo(BankAccount::USAGE_IMPREST)
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_name} ({$record->account_number})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabledOn('edit'),

                        Forms\Components\Select::make('zone_id')
                            ->label('Zone Location')
                            ->relationship('zone', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Zone $record): string => $record->town?->name
                                ? "{$record->name} ({$record->town->name})"
                                : $record->name)
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->helperText('Optional. Select a zone to make this a zone imprest fund.')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state && $zone = Zone::find($state)) {
                                    $set('location', $zone->name);
                                }
                            }),

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
                            ->options(self::statusOptions())
                            ->required()
                            ->native(false)
                            ->default('active')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Use the status actions to suspend, reactivate, or close a fund with an audit note.'),

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

                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->searchable()
                    ->toggleable(),

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
                    ->formatStateUsing(fn (string $state): string => FundStatus::tryFrom($state)?->label() ?? str($state)->title())
                    ->color(fn(string $state): string => FundStatus::tryFrom($state)?->color() ?? 'gray'),

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
                    ->options(self::statusOptions())
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
               ActionGroup::make([
                   ViewAction::make(),
                   EditAction::make(),

                   Action::make('reconcile')
                       ->icon('heroicon-m-scale')
                       ->color('warning')
                       ->url(fn(ImprestFund $record): string => ImprestReconciliationResource::getUrl('create', ['fund_id' => $record->id]))
                       ->visible(fn(ImprestFund $record): bool => $record->isActive() && auth()->user()->can('reconcile', $record)),

                   ...self::statusActions(),
               ])
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
                        TextEntry::make('zone.name')
                            ->label('Zone')
                            ->placeholder('Manual location')
                            ->icon('heroicon-m-map'),
                        TextEntry::make('bankAccount.account_name')
                            ->label('Funding Bank')
                            ->placeholder('Not linked')
                            ->icon('heroicon-m-building-library'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => FundStatus::tryFrom($state)?->label() ?? str($state)->title())
                            ->color(fn(string $state): string => FundStatus::tryFrom($state)?->color() ?? 'gray'),
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

    public static function statusActions(): array
    {
        return [
            self::makeStatusAction(
                name: 'suspend',
                label: 'Suspend Fund',
                icon: 'heroicon-m-pause-circle',
                color: 'warning',
                modalHeading: 'Suspend imprest fund',
                modalDescription: 'Suspended funds cannot receive new transactions, replenishments, or reconciliations until reactivated.',
                visible: fn (ImprestFund $record): bool => $record->canBeSuspended() && auth()->user()->can('manageStatus', $record),
                handler: fn (ImprestFund $record, array $data) => app(ImprestFundStatusService::class)->suspend($record, auth()->user(), $data['reason']),
                successTitle: 'Fund suspended',
            ),

            self::makeStatusAction(
                name: 'reactivate',
                label: 'Reactivate Fund',
                icon: 'heroicon-m-play-circle',
                color: 'success',
                modalHeading: 'Reactivate imprest fund',
                modalDescription: 'The fund will become available for new transactions and replenishments again.',
                visible: fn (ImprestFund $record): bool => $record->canBeReactivated() && auth()->user()->can('manageStatus', $record),
                handler: fn (ImprestFund $record, array $data) => app(ImprestFundStatusService::class)->reactivate($record, auth()->user(), $data['reason']),
                successTitle: 'Fund reactivated',
            ),

            self::makeStatusAction(
                name: 'close',
                label: 'Close Fund',
                icon: 'heroicon-m-lock-closed',
                color: 'danger',
                modalHeading: 'Close imprest fund',
                modalDescription: fn (ImprestFund $record): string => $record->canBeClosed()
                    ? 'Closed funds are archived from active operations. This action requires a closure note.'
                    : 'This fund cannot be closed until pending transactions and open replenishments are cleared.',
                visible: fn (ImprestFund $record): bool => ! $record->isClosed() && auth()->user()->can('manageStatus', $record),
                disabled: fn (ImprestFund $record): bool => ! $record->canBeClosed(),
                handler: fn (ImprestFund $record, array $data) => app(ImprestFundStatusService::class)->close($record, auth()->user(), $data['reason']),
                successTitle: 'Fund closed',
            ),
        ];
    }

    private static function makeStatusAction(
        string $name,
        string $label,
        string $icon,
        string $color,
        string $modalHeading,
        string|\Closure $modalDescription,
        \Closure $visible,
        \Closure $handler,
        string $successTitle,
        ?\Closure $disabled = null,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->form([
                Textarea::make('reason')
                    ->label('Reason / audit note')
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->visible($visible)
            ->disabled($disabled ?? fn (): bool => false)
            ->action(function (ImprestFund $record, array $data) use ($handler, $successTitle): void {
                try {
                    $handler($record, $data);

                    Notification::make()
                        ->title($successTitle)
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Status change failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private static function statusOptions(): array
    {
        return collect(FundStatus::cases())
            ->mapWithKeys(fn (FundStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
