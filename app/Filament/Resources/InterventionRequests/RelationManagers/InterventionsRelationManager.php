<?php

namespace App\Filament\Resources\InterventionRequests\RelationManagers;

use App\Models\BankAccount;
use App\Models\Intervention;
use App\Models\InterventionRequestItem;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InterventionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interventions';

    protected static ?string $recordTitleAttribute = 'disbursed_at';

    protected static ?string $title = 'Fulfillment History (Interventions)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('disbursed_at')
                    ->label('Delivery Date')
                    ->default(now())
                    ->required()
                    ->columnSpan(1),

                TextInput::make('collected_by')
                    ->label('Collected By')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Name of person who received items')
                    ->columnSpan(1),

                Select::make('bank_account_id')
                    ->label('Funding Bank Account')
                    ->relationship(
                        name: 'bankAccount',
                        titleAttribute: 'account_name',
                        modifyQueryUsing: fn ($query) => $query->dedicatedTo(BankAccount::USAGE_INTERVENTION)
                    )
                    ->getOptionLabelFromRecordUsing(fn (BankAccount $record) => "{$record->account_name} ({$record->account_number})")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpan(1),

                TextInput::make('amount')
                    ->label('Total Intervention Amount')
                    ->numeric()
                    ->prefix('₦')
                    ->minValue(0.01)
                    ->required()
                    ->helperText('Total amount spent/receipted for this specific delivery.')
                    ->columnSpan(1),

                // ✅ NEW: Link delivery to the specific requested items
                Repeater::make('delivered_items')
                    ->label('Items Delivered in this Batch')
                    ->schema([
                        Select::make('intervention_request_item_id')
                            ->label('Requested Item')
                            ->options(function (RelationManager $livewire) {
                                return $livewire->getOwnerRecord()->items->mapWithKeys(function ($item) {
                                    $remaining = $item->quantity_requested - $item->quantity_fulfilled;
                                    return [$item->id => "{$item->item_name} (Remaining: {$remaining})"];
                                });
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    // Inherit the master item_id and snapshot the name
                                    $requestItem = \App\Models\InterventionRequestItem::find($state);
                                    $set('item_id', $requestItem?->item_id);
                                    $set('item_name', $requestItem?->item_name);
                                }
                            }),

                        Hidden::make('item_id'),     // Inherited from the request
                        Hidden::make('item_name'),   // Inherited from the request

                        TextInput::make('quantity_delivered')
                            ->label('Qty Delivered')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                    ]),

                Textarea::make('notes')
                    ->placeholder('Specific items given or comments...')
                    ->columnSpanFull(),

                FileUpload::make('support_document_url')
                    ->label('Proof of Delivery/Photo')
                    ->directory('interventions')
                    ->columnSpanFull(),

                // Automatically link the orphan and type from the parent request
                Hidden::make('orphan_id')
                    ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->orphan_id),

                Hidden::make('intervention_type_id')
                    ->default(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->intervention_type_id),

                Hidden::make('status')
                    ->default('completed'),

                Hidden::make('disbursed_by')
                    ->default(auth()->id()),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('disbursed_at')
                    ->label('Delivery Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('collected_by')
                    ->label('Collected By')
                    ->searchable(),

                TextColumn::make('bankAccount.account_name')
                    ->label('Bank Account')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Amount Spent')
                    ->money('NGN')
                    ->alignEnd(),

                TextColumn::make('notes')
                    ->limit(50)
                    ->placeholder('No notes')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record New Delivery')
                    ->icon('heroicon-m-truck')
                    ->modalWidth('3xl')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'approved')
                    ->mutateDataUsing(function (array $data): array {
                        $data['intervention_type_id'] = $this->getOwnerRecord()->intervention_type_id;
                        $data['status'] = 'completed';
                        $data['disbursed_by'] = auth()->id();
                        $data['collected_at'] = $data['disbursed_at'] ?? now();

                        $data['intervention_request_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    })
                    ->using(function (array $data): ?Intervention {

                        // ✅ Wrap the entire transaction in a try-catch block
                        try {
                            return DB::transaction(function () use ($data): Intervention {
                                // 1. Debit the Bank Account (This throws InsufficientBankBalanceException if funds are low)
                                $bankAccount = BankAccount::lockForUpdate()->findOrFail($data['bank_account_id']);
                                $bankAccount->ensureDedicatedTo(BankAccount::USAGE_INTERVENTION, 'interventions');
                                $bankAccount->debit((float) $data['amount']);

                                // 2. Extract Repeater Data before creating Intervention
                                $deliveredItems = Arr::pull($data, 'delivered_items', []);

                                // 3. Create the Intervention Record
                                $intervention = Intervention::create($data);

                                // 4. Create the Transaction Record
                                Transaction::create([
                                    'bank_account_id' => $bankAccount->id,
                                    'transactionable_type' => Intervention::class,
                                    'transactionable_id' => $intervention->id,
                                    'reference' => 'INTV-'.strtoupper(substr($intervention->id, 0, 8)),
                                    'date' => $data['disbursed_at'] ?? now(),
                                    'type' => 'intervention',
                                    'amount' => $data['amount'],
                                    'description' => "Intervention fulfillment for {$intervention->orphan?->full_name}",
                                    'is_system' => true,
                                ]);

                                // 5. Create Intervention Items & Update Fulfilled Quantities
                                // 5. Create Intervention Items & Update Fulfilled Quantities
                                foreach ($deliveredItems as $itemData) {
                                    // Find the original requested item to inherit its snapshot data
                                    $requestItem = InterventionRequestItem::find($itemData['intervention_request_item_id']);

                                    if ($requestItem) {
                                        // Record the specific items handed out (Snapshot pattern)
                                        \App\Models\InterventionItem::create([
                                            'intervention_id' => $intervention->id,
                                            'intervention_request_item_id' => $requestItem->id,
                                            'item_name' => $requestItem->item_name, // ✅ Inherit the name
                                            'specification' => $requestItem->specification, // ✅ Inherit the spec
                                            'quantity' => $itemData['quantity_delivered'], // ✅ Map to the model's 'quantity' column
                                            // 'item_id' => $requestItem->item_id, // Uncomment if you ran the item_id migration
                                        ]);

                                        // Atomically increment the fulfilled count on the parent request item
                                        InterventionRequestItem::where('id', $itemData['intervention_request_item_id'])
                                            ->increment('quantity_fulfilled', $itemData['quantity_delivered']);
                                    }
                                }

                                return $intervention;
                            });

                        } catch (\App\Exceptions\InsufficientBankBalanceException $e) {
                            // ✅ Catch the specific exception and show a friendly notification
                            \Filament\Notifications\Notification::make()
                                ->title('Insufficient Funds')
                                ->body('The selected bank account does not have enough available balance to cover this intervention amount.')
                                ->danger()
                                ->send();

                            // Return null to halt the Filament action gracefully
                            return null;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
