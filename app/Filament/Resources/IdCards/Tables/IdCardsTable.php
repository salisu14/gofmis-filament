<?php

namespace App\Filament\Resources\IdCards\Tables;

use App\Models\IdCard;
use App\Models\IdCardPrintBatch;
use App\Models\Orphan;
use App\Models\Widow;
use App\Services\IdCardPDFService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IdCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('card_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('cardable.full_name')
                    ->label('Beneficiary Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cardable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => class_basename($state))
                    ->colors([
                        'warning' => Widow::class,
                        'success' => Orphan::class,
                    ]),

                ImageColumn::make('qr_code_path')
                    ->label('QR Code')
                    ->disk('public')
                    ->imageSize(40)
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'danger' => 'revoked',
                        'warning' => 'expired',
                    ]),

                IconColumn::make('isActive')
                    ->label('Valid')
                    ->boolean()
                    ->getStateUsing(fn(IdCard $record) => $record->isActive()),

                TextColumn::make('issued_at')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'revoked' => 'Revoked',
                        'expired' => 'Expired',
                    ]),
                SelectFilter::make('cardable_type')
                    ->label('Beneficiary Type')
                    ->options([
                        Widow::class => 'Widow',
                        Orphan::class => 'Orphan',
                    ]),
                Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn(Builder $query): Builder => $query
                        ->where('expires_at', '<=', now()->addDays(30))
                        ->where('expires_at', '>=', now())
                        ->where('status', 'active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('preview')
                        ->label('Preview PDF')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn(IdCard $record): string => route('id-cards.preview', ['card' => $record]))
                        ->openUrlInNewTab(), // Recommended so the user doesn't leave the admin panel

                    Action::make('download')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn(IdCard $record) => route(
                            'id-cards.download',
                            ['idCard' => $record]
                        ))
                        ->openUrlInNewTab(),

                    Action::make('regenerate_qr')
                        ->label('Regenerate QR')
                        ->icon('heroicon-o-qr-code')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (IdCard $record) {
                            $qrService = app(\App\Services\QRCodeService::class);
                            $newPath = $qrService->generateForCard($record);
                            $record->update(['qr_code_path' => $newPath]);
                        })
                        ->visible(fn(IdCard $record) => $record->status !== 'revoked'),

                    Action::make('revoke')
                        ->label('Revoke Card')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Revoke ID Card')
                        ->modalDescription('This will permanently invalidate this ID card.')
                        ->modalSubmitActionLabel('Yes, Revoke')
                        ->schema([
                            Textarea::make('reason')
                                ->label('Revocation Reason')
                                ->required()
                                ->minLength(10),
                        ])
                        ->action(function (IdCard $record, array $data) {
                            $record->revoke($data['reason']);
                        })
                        ->visible(fn(IdCard $record) => $record->status === 'active'),

                    Action::make('reactivate')
                        ->label('Reactivate Card')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Reactivate ID Card')
                        ->modalDescription('This will restore the ID card back to an active state.')
                        ->modalSubmitActionLabel('Yes, Reactivate')
                        ->schema([
                            Textarea::make('reason')
                                ->label('Reason for Reactivation')
                                ->required()
                                ->minLength(10),
                        ])
                        ->action(function (IdCard $record, array $data) {
                            $record->update([
                                'status' => 'active',
                                'revocation_reason' => null,
                            ]);

                            Notification::make()
                                ->title('Card Reactivated')
                                ->success()
                                ->send();
                        })
                        ->visible(fn(IdCard $record) => $record->status === 'revoked'),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('print_selected')
                        ->label('Print Selected')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->title('No cards selected')
                                    ->warning()
                                    ->send();

                                return null;
                            }

                            $type = $records
                                ->pluck('cardable_type')
                                ->unique()
                                ->count() === 1
                                    ? ($records->first()->cardable_type === Widow::class ? 'widow' : 'orphan')
                                    : 'mixed';

                            /** @var IdCardPrintBatch $batch */
                            $batch = IdCardPrintBatch::create([
                                'batch_name' => 'Selected ID Cards - '.now()->format('Y-m-d H:i'),
                                'type' => $type,
                                'filters' => [
                                    'source' => 'selected_id_cards',
                                    'card_ids' => $records->pluck('id')->values()->all(),
                                ],
                                'range' => null,
                                'total_count' => $records->count(),
                                'processed_count' => 0,
                                'status' => 'processing',
                                'started_at' => now(),
                                'created_by' => auth()->id(),
                            ]);

                            $records->loadMissing(['cardable.deceased.zone.coordinator', 'template']);

                            try {
                                $pdfPath = app(IdCardPDFService::class)->generateBulk($records->values(), $batch);

                                $batch->update([
                                    'pdf_path' => $pdfPath,
                                    'processed_count' => $records->count(),
                                    'status' => 'completed',
                                    'completed_at' => now(),
                                ]);

                                $records->each->markAsPrinted();

                                Notification::make()
                                    ->title('Print batch ready')
                                    ->body("Generated {$records->count()} ID cards for printing.")
                                    ->success()
                                    ->send();

                                return redirect()->route('id-card-print-batches.download', ['record' => $batch]);
                            } catch (\Throwable $exception) {
                                $batch->update([
                                    'status' => 'failed',
                                    'completed_at' => now(),
                                ]);

                                Notification::make()
                                    ->title('Unable to generate print batch')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return null;
                            }
                        }),

                    BulkAction::make('activate')
                        ->label('Activate Cards')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['status' => 'active', 'issued_at' => now()])
                        ),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
