<?php

namespace App\Filament\Resources\Widows\Tables;

use App\Filament\Exports\WidowExporter;
use App\Filament\Resources\IdCards\IdCardResource;
use App\Filament\Resources\Widows\Actions\GenerateIdCardAction;
use App\Models\Deceased;
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
                ImageColumn::make('picture_url')
                    ->label('Image')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->checkFileExistence(false),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
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
                        ->url(fn(Widow $record) => $record->idCards()->where('status', 'active')->first()
                            ? IdCardResource::getUrl('view', [
                                'record' => $record->idCards()->where('status', 'active')->first()
                            ])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn(Widow $record): bool => $record->idCards()->where('status', 'active')->exists()
                        ),

                    Action::make('print_card')
                        ->label('Print Card')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn(Widow $record) => ($card = $record->idCards()->where('status', 'active')->first())
                            ? route('id-cards.download', ['idCard' => $card])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn(Widow $record): bool => $record->idCards()->where('status', 'active')->exists()
                        ),

                    Action::make('markAsMarried')
                        ->label('Mark Married')
                        ->icon('heroicon-m-heart')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Mark as Married')
                        ->modalDescription('This will revoke all benefits and eligibility. This action cannot be undone.')
                        ->modalSubmitActionLabel('Yes, Mark as Married')
                        ->visible(fn($record) => !$record->is_married)
                        ->schema([
                            DatePicker::make('married_at')
                                ->label('Marriage Date')
                                ->default(now())
                                ->required(),
                            Textarea::make('notes')
                                ->label('Notes')
                                ->placeholder('Optional notes about the marriage...')
                                ->rows(2),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'is_married' => true,
                                'married_at' => $data['married_at'] ?? now(),
                            ]);

                            // Call the model method to handle side effects
                            $record->markAsMarried($data['notes'] ?? null);

                            Notification::make()
                                ->title('Marked as Married')
                                ->body("{$record->full_name} has been marked as married and removed from benefits.")
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make(),
                ])
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
                                if (!$record->is_married) {
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
