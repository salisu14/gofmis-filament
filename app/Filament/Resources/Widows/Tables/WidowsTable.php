<?php

namespace App\Filament\Resources\Widows\Tables;

use App\Filament\Exports\WidowExporter;
use App\Filament\Resources\IdCards\IdCardResource;
use App\Filament\Resources\Widows\Actions\GenerateIdCardAction;
use App\Models\Widow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class WidowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->groups([
                Group::make('zone.name')
                    ->label('Zone'),

                Group::make('deceased.vulnerability_status')
                    ->label('Vulnerability')
                    ->getTitleFromRecordUsing(fn (Widow $record): string => $record->deceased?->vulnerability_status?->getLabel() ?? 'N/A')
                    ->collapsible(),
            ])
            ->columns([
                ImageColumn::make('profile_photo_url')
                    ->label('Profile Photo')
                    ->circular()
                    ->checkFileExistence(false)
                    ->defaultImageUrl(url('/images/placeholder-avatar.png')),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->state(fn ($record): string => (string) $record->display_name)
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('full_name', $direction))
                    ->weight('bold'),

                TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->searchable()
                    ->badge()
                    ->sortable(),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->searchable(),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->searchable(),

                TextColumn::make('deceased.vulnerability_status')
                    ->label('Vulnerability Status')
                    ->alignCenter()
                    ->searchable(),

                TextColumn::make('deceased.zone.coordinator.name')
                    ->label('Coordinator')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('skills')
                    ->label('Skills')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),

                TextColumn::make('deceased.full_name')
                    ->label('Deceased Head')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(WidowExporter::class)
                    ->enableVisibleTableColumnsByDefault(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // ID Card Actions
                    GenerateIdCardAction::make(),

                    Action::make('view_card')
                        ->label('View ID Card')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (Widow $record) => $record->idCards()->whereIn('status', ['draft', 'active'])->latest()->first()
                            ? IdCardResource::getUrl('view', [
                                'record' => $record->idCards()->whereIn('status', ['draft', 'active'])->latest()->first(),
                            ])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn (Widow $record): bool => $record->idCards()->whereIn('status', ['draft', 'active'])->exists()
                        ),

                    Action::make('print_card')
                        ->label('Print Card')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn (Widow $record) => ($card = $record->idCards()->whereIn('status', ['draft', 'active'])->latest()->first())
                            ? route('id-cards.download', ['idCard' => $card])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn (Widow $record): bool => $record->idCards()->whereIn('status', ['draft', 'active'])->exists()
                        ),

                    Action::make('markAsMarried')
                        ->label('Mark as Remarried')
                        ->icon('heroicon-m-heart')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Mark as Remarried')
                        ->modalDescription('This will mark the widow relationship under this household as remarried and revoke active benefits. All historical loans, repayments, and records remain preserved.')
                        ->modalSubmitActionLabel('Yes, Mark as Remarried')
                        ->visible(fn ($record) => ! $record->is_married)
                        ->schema([
                            DatePicker::make('married_at')
                                ->label('Remarriage Date')
                                ->default(now())
                                ->maxDate(now())
                                ->required()
                                ->rule(function ($record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($record) {
                                        $date = \Illuminate\Support\Carbon::parse($value);

                                        if ($date->isFuture()) {
                                            $fail('Remarriage date cannot be in the future.');

                                            return;
                                        }

                                        $dateOfDeath = $record->deceased?->date_of_death;

                                        if ($dateOfDeath && $date->lt(\Illuminate\Support\Carbon::parse($dateOfDeath))) {
                                            $fail('Remarriage date cannot be earlier than the deceased husband\'s date of death ('.\Illuminate\Support\Carbon::parse($dateOfDeath)->format('d M, Y').').');
                                        }
                                    };
                                }),
                            Textarea::make('notes')
                                ->label('Notes')
                                ->placeholder('Optional notes about the remarriage...')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->markAsMarried(
                                notes: $data['notes'] ?? null,
                                marriedAt: $data['married_at'] ?? null
                            );

                            Notification::make()
                                ->title('Marked as Remarried')
                                ->body("{$record->full_name} has been marked as remarried.")
                                ->success()
                                ->send();
                        }),

                    Action::make('reactivateAfterDivorce')
                        ->label('Reactivate After Divorce')
                        ->icon('heroicon-m-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Reactivate Widow After Divorce')
                        ->modalDescription('This action should only be used when the later marriage ended in divorce. If the later husband died, do not reactivate this record; register/create the widow under the later deceased husband\'s household instead.')
                        ->modalSubmitActionLabel('Yes, Reactivate')
                        ->visible(fn ($record) => (bool) $record->is_married)
                        ->schema([
                            DatePicker::make('divorced_at')
                                ->label('Divorce / Reactivation Date')
                                ->default(now())
                                ->maxDate(now())
                                ->required()
                                ->rule(function ($record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($record) {
                                        $date = \Illuminate\Support\Carbon::parse($value);

                                        if ($date->isFuture()) {
                                            $fail('Divorce / reactivation date cannot be in the future.');

                                            return;
                                        }

                                        if ($record->married_at && $date->lt(\Illuminate\Support\Carbon::parse($record->married_at))) {
                                            $fail('Divorce date cannot be earlier than the recorded remarriage date ('.\Illuminate\Support\Carbon::parse($record->married_at)->format('d M, Y').').');
                                        }
                                    };
                                }),
                            Textarea::make('notes')
                                ->label('Notes')
                                ->placeholder('Optional notes about the divorce/reactivation...')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->reactivateAfterDivorce(
                                notes: $data['notes'] ?? null,
                                divorcedAt: $data['divorced_at'] ?? null
                            );

                            Notification::make()
                                ->title('Widow Reactivated')
                                ->body("{$record->full_name} has been reactivated following divorce.")
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),

                    BulkAction::make('markAsMarried')
                        ->label('Mark Selected as Married')
                        ->icon('heroicon-m-heart')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Multiple as Married')
                        ->modalDescription('This will revoke benefits for all selected beneficiaries.')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (! $record->is_married) {
                                    $record->markAsMarried();
                                }
                            }

                            Notification::make()
                                ->title('Completed')
                                ->body("{$records->count()} beneficiaries marked as married.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
