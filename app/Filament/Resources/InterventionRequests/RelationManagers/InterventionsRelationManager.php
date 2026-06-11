<?php

namespace App\Filament\Resources\InterventionRequests\RelationManagers;

use App\Models\BankAccount;
use App\Models\Intervention;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                TextInput::make('collected_by')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Name of person who received items'),

                DatePicker::make('disbursed_at')
                    ->label('Delivery Date')
                    ->default(now())
                    ->required(),

                Select::make('bank_account_id')
                    ->label('Funding Bank Account')
                    ->relationship('bankAccount', 'account_name')
                    ->getOptionLabelFromRecordUsing(fn (BankAccount $record) => "{$record->account_name} ({$record->account_number})")
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('amount')
                    ->label('Intervention Amount')
                    ->numeric()
                    ->prefix('₦')
                    ->minValue(0.01)
                    ->required(),

                FileUpload::make('support_document_url')
                    ->label('Proof of Delivery/Photo')
                    ->directory('interventions')
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->placeholder('Specific items given or comments...')
                    ->columnSpanFull(),

                // Automatically link the orphan from the parent request
                Hidden::make('orphan_id')
                    ->default(fn(RelationManager $livewire) => $livewire->getOwnerRecord()->orphan_id),

                Hidden::make('intervention_type_id')
                    ->default(fn(RelationManager $livewire) => $livewire->getOwnerRecord()->intervention_type_id),

                Hidden::make('status')
                    ->default('completed'),

                Hidden::make('disbursed_by')
                    ->default(auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('disbursed_at')
                    ->label('Delivery Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('collected_by')
                    ->searchable(),

                TextColumn::make('bankAccount.account_name')
                    ->label('Bank')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->alignEnd(),

                TextColumn::make('notes')
                    ->limit(50)
                    ->placeholder('No notes'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record New Delivery')
                    ->icon('heroicon-m-truck')
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
                            $bankAccount = BankAccount::lockForUpdate()->findOrFail($data['bank_account_id']);
                            $bankAccount->debit((float) $data['amount']);

                            $intervention = Intervention::create($data);

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

                            return $intervention;
                        });
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
