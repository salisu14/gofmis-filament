<?php

namespace App\Filament\Resources\Prescriptions\Tables;

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PrescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prescription_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('prescribable.full_name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Prescription $record) => str_replace('App\Models\\', '', $record->prescribable_type)),

                TextColumn::make('illnessModel.name')
                    ->label('Diagnosis')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Treatment Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('treated_at')
                    ->label('Completed At')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('medications.name')
                    ->label('Meds')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('NGN')
                    ->state(fn (Prescription $record) => $record->total_cost)
                    ->color('success')
                    ->weight('bold'),

                TextColumn::make('doctor_name')
                    ->label('Doctor')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Issued By')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Treatment Status')
                    ->options(PrescriptionStatus::class),

                SelectFilter::make('prescribable_type')
                    ->label('Patient Type')
                    ->options([
                        'App\Models\Orphan' => 'Orphan',
                        'App\Models\Widow' => 'Widow',
                    ]),

                Filter::make('prescription_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('prescription_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('prescription_date', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                \App\Filament\Actions\MarkHealthcareTreatedAction::make(),
                ActionGroup::make([
                    \Filament\Actions\Action::make('edit_prescription')
                        ->label('Edit')
                        ->icon('heroicon-m-pencil-square')
                        ->url(fn (Prescription $record): string => \App\Filament\Resources\Prescriptions\PrescriptionResource::getUrl('edit', ['record' => $record]))
                        ->hidden(fn (Prescription $record) => ! $record->isPending()),
                    \Filament\Actions\Action::make('preview_pdf')
                        ->label('Preview Prescription PDF')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (Prescription $record): string => route('prescriptions.preview', ['prescription' => $record]))
                        ->openUrlInNewTab(),
                    \Filament\Actions\Action::make('download_pdf')
                        ->label('Download Prescription PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Prescription $record): string => route('prescriptions.download', ['prescription' => $record]))
                        ->openUrlInNewTab(),
                    \Filament\Actions\Action::make('preview_referral')
                        ->label('Preview Referral Form')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->url(fn (Prescription $record): string => route('prescriptions.referral.preview', ['prescription' => $record]))
                        ->openUrlInNewTab(),
                    \Filament\Actions\Action::make('download_referral')
                        ->label('Download Referral Form')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->url(fn (Prescription $record): string => route('prescriptions.referral.download', ['prescription' => $record]))
                        ->openUrlInNewTab(),
                    DeleteAction::make()
                        ->visible(fn (Prescription $record) => $record->isPending()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $deletable = $records->reject(fn (Prescription $record) => $record->isTreated());
                            $protectedCount = $records->count() - $deletable->count();

                            $deletable->each(fn (Prescription $record) => $record->delete());

                            if ($protectedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Protected Clinical Records Skipped')
                                    ->body("{$protectedCount} completed healthcare request(s) were protected from deletion.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
