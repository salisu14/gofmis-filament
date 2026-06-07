<?php

namespace App\Filament\Resources\WelfarePackages\RelationManagers;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Models\WelfareBeneficiary;
use App\Services\BeneficiaryService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BeneficiariesRelationManager extends RelationManager
{
    protected static string $relationship = 'beneficiaries';

    protected static ?string $title = 'Beneficiaries';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('deceased_id')
                    ->relationship(
                        'deceased',
                        'full_name',
                        modifyQueryUsing: fn(Builder $query): Builder => $query
                    )
                    ->getOptionLabelFromRecordUsing(fn($record): string => "{$record->full_name} - {$record->reg_no}"
                    )
                    ->searchable(['full_name', 'reg_no'])
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),

                Select::make('status')
                    ->options(BeneficiaryStatus::class)
                    ->required()
                    ->visibleOn('edit'),

                Textarea::make('rejection_reason')
                    ->visible(fn(Get $get) => $get('status') === BeneficiaryStatus::REJECTED->value)
                    ->required(fn(Get $get) => $get('status') === BeneficiaryStatus::REJECTED->value),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('deceased.full_name')
            ->columns([
                TextColumn::make('deceased.full_name')
                    ->label('Deceased Name')
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold'),

                TextColumn::make('deceased.last_name')
                    ->searchable()
                    ->sortable()
                    ->label('Family'),

                TextColumn::make('suggester.name')
                    ->label('Suggested By')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(BeneficiaryStatus $state): string => $state->color())
                    ->icon(fn(BeneficiaryStatus $state): string => $state->icon()),

                TextColumn::make('collection_status')
                    ->badge()
                    ->color(fn(CollectionStatus $state): string => $state->color())
                    ->icon(fn(CollectionStatus $state): string => $state->icon()),

                TextColumn::make('collected_at')
                    ->dateTime('M d, Y H:i')
                    ->toggleable()
                    ->placeholder('Not collected'),

                TextColumn::make('collector.name')
                    ->label('Collected By')
                    ->toggleable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BeneficiaryStatus::class),

                SelectFilter::make('collection_status')
                    ->options(CollectionStatus::class),

                Filter::make('ready_for_collection')
                    ->query(fn($query) => $query->readyForCollection())
                    ->toggle(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Suggest Beneficiary')
                    ->visible(fn() => $this->getOwnerRecord()->isOpen() && auth()->user()?->can('suggest', WelfareBeneficiary::class))
                    ->mutateDataUsing(function (array $data) {
                        $data['suggested_by'] = auth()->id();
                        $data['status'] = BeneficiaryStatus::PENDING;
                        $data['collection_status'] = CollectionStatus::NOT_COLLECTED;
                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(WelfareBeneficiary $record): bool => (auth()->user()?->can('approve', $record) ?? false)
                            && $record->canBeApproved()
                        )
                        ->action(function (WelfareBeneficiary $record) {
                            app(BeneficiaryService::class)->approveBeneficiary($record);
                        }),

                    Action::make('reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->schema([
                            Textarea::make('reason')
                                ->required()
                                ->label('Rejection Reason'),
                        ])
                        ->visible(fn(WelfareBeneficiary $record): bool => (auth()->user()?->can('reject', $record) ?? false)
                            && $record->canBeRejected()
                        )
                        ->action(function (WelfareBeneficiary $record, array $data) {
                            app(BeneficiaryService::class)->rejectBeneficiary($record, $data['reason']);
                        }),

                    Action::make('collect')
                        ->icon('heroicon-o-check-badge')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Mark as Collected')
                        ->schema([
                            Textarea::make('notes')
                                ->label('Collection Notes'),
                        ])
                        ->visible(fn(WelfareBeneficiary $record): bool => (auth()->user()?->can('collect', $record) ?? false)
                            && $record->canBeCollected()
                        )
                        ->action(function (WelfareBeneficiary $record, array $data) {
                            app(BeneficiaryService::class)->collectPackage(
                                $record,
                                $data['notes'] ?? null
                            );
                        }),

                    EditAction::make()
                        ->visible(fn(WelfareBeneficiary $record): bool => auth()->user()?->can('view', $record) && $record->isPending()),

                    DeleteAction::make()
                        ->visible(fn(WelfareBeneficiary $record): bool => auth()->user()?->can('delete', $record) ?? false),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(): bool => auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false)
                        ->action(function ($records) {
                            app(BeneficiaryService::class)->bulkApprove($records->pluck('id')->toArray());
                        }),

                    BulkAction::make('collect')
                        ->icon('heroicon-o-check-badge')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn(): bool => auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false)
                        ->action(function ($records) {
                            app(BeneficiaryService::class)->bulkCollect($records->pluck('id')->toArray());
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
