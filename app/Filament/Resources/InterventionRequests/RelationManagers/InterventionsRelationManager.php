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
                    ->relationship('bankAccount', 'account_name')
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

                        return $data;
                    })
                    ->using(function (array $data): Intervention {
                        return DB::transaction(function () use ($data): Intervention {
                            // 1. Debit the Bank Account
                            $bankAccount = BankAccount::lockForUpdate()->findOrFail($data['bank_account_id']);
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

                            // 5. ✅ NEW: Create Intervention Items & Update Fulfilled Quantities
                            foreach ($deliveredItems as $itemData) {
                                // Record the specific items handed out
                                \App\Models\InterventionItem::create([
                                    'intervention_id' => $intervention->id,
                                    'intervention_request_item_id' => $itemData['intervention_request_item_id'],
                                    'quantity' => $itemData['quantity_delivered'],
                                ]);

                                // Atomically increment the fulfilled count on the parent request item
                                InterventionRequestItem::where('id', $itemData['intervention_request_item_id'])
                                    ->increment('quantity_fulfilled', $itemData['quantity_delivered']);
                            }

                            return $intervention;
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
