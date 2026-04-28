<?php

namespace App\Filament\Resources\IdCards\Tables;

use App\Models\IdCard;
use App\Models\Orphan;
use App\Models\Widow;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
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
                ViewAction::make(),
                EditAction::make(),

                Action::make('preview')
                    ->label('Preview PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn(IdCard $record): string => route('filament.admin.resources.id-cards.preview', ['record' => $record])),

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
                    ->form([
                        Textarea::make('reason')
                            ->label('Revocation Reason')
                            ->required()
                            ->minLength(10),
                    ])
                    ->action(function (IdCard $record, array $data) {
                        $record->revoke($data['reason']);
                    })
                    ->visible(fn(IdCard $record) => $record->status === 'active'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('print_selected')
                        ->label('Print Selected')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $ids = $records->pluck('id')->toArray();
                            return redirect()->route('filament.admin.resources.id-cards.bulk-print', [
                                'ids' => implode(',', $ids)
                            ]);
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
