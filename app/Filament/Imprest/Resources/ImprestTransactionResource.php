<?php

namespace App\Filament\Imprest\Resources;

use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Filament\Imprest\Resources\ImprestTransactionResource\Pages\CreateImprestTransaction;
use App\Filament\Imprest\Resources\ImprestTransactionResource\Pages\EditImprestTransaction;
use App\Filament\Imprest\Resources\ImprestTransactionResource\Pages\ListImprestTransactions;
use App\Filament\Imprest\Resources\ImprestTransactionResource\Pages\ViewImprestTransaction;
use App\Models\ImprestTransaction;
use App\Services\Contracts\Imprest\ImprestTransactionServiceInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ImprestTransactionResource extends Resource
{
    protected static ?string $model = ImprestTransaction::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';
    protected static string|null|\UnitEnum $navigationGroup = 'Transactions';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'voucher_no';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['fund', 'custodian', 'approver']);;

        // Non-admins only see their fund's transactions or those they can approve
        if (!auth()->user()->hasRole('admin')) {
            $query->where(function ($q) {
                $q->where('custodian_id', auth()->id())
                    ->orWhereHas('fund', fn($fq) => $fq->where('custodian_id', auth()->id()));
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Transaction Details')
                    ->columns(2)
                    ->schema([
                        Select::make('fund_id')
                            ->relationship('fund', 'location')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->default(fn() => request()->query('fund_id'))
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('custodian_id', auth()->id());
                            }),

                        DatePicker::make('date')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->native(false),

                        TextInput::make('deceased_id')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('DEC-12345')
                            ->prefixIcon('heroicon-m-identification'),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Deceased person name'),

                        TextInput::make('item_service')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Description of item or service'),

                        Select::make('category')
                            ->options(collect(TransactionCategory::cases())->mapWithKeys(
                                fn($case) => [$case->value => $case->label()]
                            ))
                            ->required()
                            ->native(false),

                        Select::make('payment_method')
                            ->options(collect(PaymentMethod::cases())->mapWithKeys(
                                fn($case) => [$case->value => $case->getLabel()]
                            ))
                            ->required()
                            ->default(PaymentMethod::CASH->value)
                            ->native(false),
                    ]),

                Section::make('Financial Details')
                    ->columns(3)
                    ->schema([
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->step(0.01)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $qty = floatval($get('quantity') ?? 0);
                                $price = floatval($get('unit_price') ?? 0);
                                $set('total_price', round($qty * $price, 2));
                            }),

                        TextInput::make('unit_price')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->minValue(0)
                            ->step(0.01)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $qty = floatval($get('quantity') ?? 0);
                                $price = floatval($get('unit_price') ?? 0);
                                $set('total_price', round($qty * $price, 2));
                            }),

                        TextInput::make('total_price')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated()
                            ->default(0),

                        Toggle::make('receipt_attached')
                            ->label('Receipt Attached')
                            ->helperText('Confirm physical receipt is on file')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),

                Hidden::make('custodian_id')
                    ->default(auth()->id()),

                Section::make('Internal Use')
                    ->columns(2)
                    ->schema([
                        TextInput::make('voucher_no')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated on save')
                            ->helperText('Voucher number will be assigned automatically'),

                        Placeholder::make('status_display')
                            ->label('Status')
                            ->content('Pending Approval'),
                    ])
                    ->hiddenOn('edit')
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher_no')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn(ImprestTransaction $record): string => match ($record->status) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'voided' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->copyable(),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fund.location')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Deceased')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('item_service')
                    ->searchable()
                    ->limit(25)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->alignment('right')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unit_price')
                    ->money('NGN')
                    ->alignment('right')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_price')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right')
                    ->weight('font-bold'),

                Tables\Columns\IconColumn::make('receipt_attached')
                    ->boolean()
                    ->label('Receipt')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('custodian.name')
                    ->label('Custodian')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'voided' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'voided' => 'Voided',
                        'rejected' => 'Rejected',
                    ])
                    ->multiple()
                    ->native(false),

                Tables\Filters\SelectFilter::make('fund_id')
                    ->relationship('fund', 'location')
                    ->searchable()
                    ->preload()
                    ->label('Fund')
                    ->native(false),

                Tables\Filters\SelectFilter::make('category')
                    ->options(collect(TransactionCategory::cases())->mapWithKeys(
                        fn($case) => [$case->value => $case->label()]
                    ))
                    ->multiple()
                    ->native(false),

                Tables\Filters\Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('date', '<=', $date));
                    }),

                Tables\Filters\TernaryFilter::make('receipt_attached')
                    ->label('Receipt Status')
                    ->placeholder('All')
                    ->trueLabel('Attached')
                    ->falseLabel('Missing'),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Transaction')
                    ->modalDescription('This will deduct the amount from the fund balance.')
                    ->modalSubmitActionLabel('Approve')
                    ->visible(fn(ImprestTransaction $record): bool => $record->status === 'pending' && auth()->user()->can('approve', $record)
                    )
                    ->action(function (ImprestTransaction $record) {
                        $service = app(ImprestTransactionServiceInterface::class);
                        $service->approve(new \App\Data\Imprest\ApproveTransactionDto(
                            transactionId: $record->id,
                            approvedBy: auth()->id(),
                        ));

                        Notification::make()
                            ->title('Transaction Approved')
                            ->success()
                            ->body("Voucher {$record->voucher_no} has been approved.")
                            ->send();
                    })
                    ->after(function () {
                        // Refresh the table to show updated status
                    }),

                Action::make('void')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('reason')
                            ->required()
                            ->minLength(10)
                            ->placeholder('Provide detailed reason for voiding this transaction'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Void Transaction')
                    ->modalDescription('This action cannot be undone. The fund balance will be restored if already deducted.')
                    ->visible(fn(ImprestTransaction $record): bool => $record->isVoidable() && auth()->user()->can('void', $record)
                    )
                    ->action(function (ImprestTransaction $record, array $data) {
                        $service = app(ImprestTransactionServiceInterface::class);
                        $service->void(new \App\Data\Imprest\VoidTransactionDto(
                            transactionId: $record->id,
                            voidedBy: auth()->id(),
                            reason: $data['reason'],
                        ));

                        Notification::make()
                            ->title('Transaction Voided')
                            ->danger()
                            ->body("Voucher {$record->voucher_no} has been voided.")
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn(ImprestTransaction $record): bool => $record->status === 'pending'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn(): bool => auth()->user()->hasRole('admin')),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Transaction Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('voucher_no')
                            ->badge()
                            ->color(fn(ImprestTransaction $record): string => match ($record->status) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'voided' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('date')->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'voided' => 'danger',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Deceased Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('deceased_id')
                            ->label('Deceased ID')
                            ->icon('heroicon-m-identification'),
                        TextEntry::make('name')
                            ->label('Name'),
                    ]),

                Section::make('Purchase Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('item_service'),
                        TextEntry::make('category')->badge(),
                        TextEntry::make('payment_method')->badge(),
                        TextEntry::make('receipt_attached')
                            ->icon(fn(bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                            ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                    ]),

                Section::make('Financial Breakdown')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('quantity')->numeric(decimalPlaces: 2),
                        TextEntry::make('unit_price')->money('NGN'),
                        TextEntry::make('total_price')
                            ->money('NGN')
                            ->weight('font-bold')
                            ->size(TextSize::Large),
                    ]),

                Section::make('Audit Trail')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('custodian.name')->label('Created By'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('approver.name')->label('Approved By')->placeholder('Not yet approved'),
                        TextEntry::make('approved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('void_reason')
                            ->columnSpanFull()
                            ->visible(fn(ImprestTransaction $record): bool => $record->status === 'voided'),
                    ]),
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->voucher_no;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Deceased' => $record->name,
            'Amount' => '₦' . number_format($record->total_price, 2),
            'Status' => $record->status,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['fund']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImprestTransactions::route('/'),
            'create' => CreateImprestTransaction::route('/create'),
            'view' => ViewImprestTransaction::route('/{record}'),
            'edit' => EditImprestTransaction::route('/{record}/edit'),
        ];
    }
}
