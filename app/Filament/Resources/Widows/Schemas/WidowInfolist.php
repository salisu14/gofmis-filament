<?php

namespace App\Filament\Resources\Widows\Schemas;

use App\Models\Widow;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                ImageEntry::make('picture_url')
                                    ->label('')
                                    ->disk('public')
                                    ->circular()
                                    ->defaultImageUrl(url('/images/placeholder-avatar.png')),

                                TextEntry::make('full_name')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->columnSpan(3),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('nin')->copyable(),
                                TextEntry::make('reg_no')->copyable(),
                                TextEntry::make('child_sequence'),
                            ]),

                        TextEntry::make('address')
                            ->columnSpanFull(),
                    ]),

                Section::make()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('is_eligible')
                                    ->boolean(),
                                IconEntry::make('is_married')
                                    ->boolean(),

                                TextEntry::make('deceased.vulnerability_status')
                                    ->label('Vulnerability Status')
                                    ->badge(),

                                TextEntry::make('zone.name')
                                    ->label('Zone')
                                    ->badge('success'),

                                TextEntry::make('deceased.zone.coordinator.name')
                                    ->label('Coordinator')
                                    ->badge('primary'),
                            ]),

                        TextEntry::make('married_at')
                            ->label('Remarriage Date')
                            ->dateTime()
                            ->placeholder('N/A')
                            ->visible(fn (Widow $record): bool => (bool) $record->married_at),

                        TextEntry::make('divorced_at')
                            ->label('Divorce / Reactivation Date')
                            ->dateTime()
                            ->placeholder('N/A')
                            ->visible(fn (Widow $record): bool => (bool) $record->divorced_at),

                        TextEntry::make('skills')
                            ->badge()
                            ->separator(','),

                        TextEntry::make('deceased.full_name')
                            ->label('Spouse'),

                    ]),

                Section::make('ID Card Overview')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        TextEntry::make('latest_id_card_number')
                            ->label('Card Number')
                            ->getStateUsing(fn (Widow $record) => $record->idCards()->latest()->first()?->card_number ?? 'No ID Card Issued')
                            ->badge(),

                        TextEntry::make('latest_id_card_status')
                            ->label('Card Status')
                            ->getStateUsing(fn (Widow $record) => ucfirst($record->idCards()->latest()->first()?->status ?? 'Not Issued'))
                            ->badge()
                            ->color(fn (string $state): string => match (strtolower($state)) {
                                'active' => 'success',
                                'draft' => 'gray',
                                'revoked' => 'danger',
                                'expired' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('latest_id_card_validity')
                            ->label('Validity')
                            ->getStateUsing(function (Widow $record): string {
                                $card = $record->idCards()->latest()->first();
                                if (! $card) {
                                    return 'N/A';
                                }

                                return $card->isActive() ? 'Valid' : 'Invalid / Inactive';
                            })
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Valid' ? 'success' : 'danger'),

                        TextEntry::make('latest_id_card_issued_at')
                            ->label('Issued At')
                            ->getStateUsing(fn (Widow $record) => $record->idCards()->latest()->first()?->issued_at)
                            ->date('d M, Y')
                            ->placeholder('N/A'),

                        TextEntry::make('latest_id_card_expires_at')
                            ->label('Expires At')
                            ->getStateUsing(fn (Widow $record) => $record->idCards()->latest()->first()?->expires_at)
                            ->date('d M, Y')
                            ->placeholder('N/A'),
                    ])->columns(3),

                Section::make('Marital Lifecycle History')
                    ->icon('heroicon-m-clock')
                    ->description('Append-only audit log of marital state changes for this household relationship.')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('marital_lifecycle_activities')
                            ->label('')
                            ->getStateUsing(function (Widow $record) {
                                return \Illuminate\Support\Facades\DB::table('activities')
                                    ->where('subject_type', Widow::class)
                                    ->where('subject_id', (string) $record->id)
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->map(function ($a) {
                                        $props = json_decode($a->properties ?? '{}', true) ?: [];
                                        $causer = $a->causer_id ? \App\Models\User::find($a->causer_id)?->name : 'System';
                                        $eventType = $props['event_type'] ?? match ($a->description) {
                                            'widow_marked_married', 'REMARRIED' => 'REMARRIED',
                                            'REACTIVATED_AFTER_DIVORCE' => 'REACTIVATED_AFTER_DIVORCE',
                                            'NEW_WIDOW_HOUSEHOLD_CREATED' => 'NEW_WIDOW_HOUSEHOLD_CREATED',
                                            default => strtoupper($a->description ?: 'REGISTERED_AS_WIDOW'),
                                        };
                                        $effectiveDate = $props['married_at'] ?? $props['divorced_at'] ?? $a->created_at;

                                        return [
                                            'event_type' => str_replace('_', ' ', $eventType),
                                            'effective_date' => $effectiveDate ? \Illuminate\Support\Carbon::parse($effectiveDate)->format('d M, Y H:i') : 'N/A',
                                            'performed_by' => $causer ?: 'System',
                                            'notes' => $props['notes'] ?? 'No notes provided',
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->schema([
                                TextEntry::make('event_type')
                                    ->label('Event')
                                    ->badge()
                                    ->color(fn ($state) => match (strtoupper($state ?? '')) {
                                        'REMARRIED' => 'danger',
                                        'REACTIVATED AFTER DIVORCE' => 'success',
                                        'NEW WIDOW HOUSEHOLD CREATED' => 'info',
                                        default => 'primary',
                                    }),
                                TextEntry::make('effective_date')
                                    ->label('Effective Date'),
                                TextEntry::make('performed_by')
                                    ->label('Logged By'),
                                TextEntry::make('notes')
                                    ->label('Notes / Reason'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
