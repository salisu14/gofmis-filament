<?php

namespace App\Filament\Resources\Orphans\Schemas;

use App\Enums\OrphanStatus;
use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrphanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile Summary')
                    ->schema([
                        ImageEntry::make('profile_photo_url')
                            ->label('Profile Photo')
                            ->circular()
                            ->defaultImageUrl(url('/images/placeholder-avatar.png')),

                        TextEntry::make('full_name')
                            ->label('Full Name')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('reg_no')
                            ->label('Registration ID')
                            ->copyable()
                            ->badge(),
                        TextEntry::make('gender')
                            ->badge(),
                        TextEntry::make('age')
                            ->label('Age')
                            ->getStateUsing(fn ($record) => $record->birth_date ? Carbon::parse($record->birth_date)->age : null
                            )
                            ->suffix(' Years Old'),
                        TextEntry::make('deceased.vulnerability_status')
                            ->label('Vulnerability Status')
                            ->badge(),
                    ])->columns(4),

                Section::make('Eligibility & Status')
                    ->schema([
                        IconEntry::make('is_eligible')
                            ->label('Eligible')
                            ->boolean(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (OrphanStatus $state): string => match ($state) {
                                OrphanStatus::ACTIVE->value => 'success',
                                OrphanStatus::PENDING_REVIEW->value => 'warning',
                                OrphanStatus::REJECTED->value => 'danger',
                                OrphanStatus::ARCHIVED->value => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('nin')
                            ->label('NIN')
                            ->copyable()
                            ->placeholder('Not Provided'),
                        TextEntry::make('deceased.full_name')
                            ->label('Parent Record')
                            ->placeholder('Unknown'),
                        IconEntry::make('has_birth_cert')
                            ->label('Birth Certificate')
                            ->boolean(),
                        TextEntry::make('birth_certificate_path')
                            ->label('Certificate Link')
                            ->url(fn ($record) => $record->birth_certificate_path ? asset('storage/'.$record->birth_certificate_path) : null)
                            ->openUrlInNewTab()
                            ->visible(fn ($record) => $record->has_birth_cert && $record->birth_certificate_path)
                            ->placeholder('No file uploaded')
                            ->icon('heroicon-m-link'),
                    ])->columns(3),

                Section::make('Personal Details')
                    ->schema([
                        TextEntry::make('birth_date')
                            ->date('d M, Y'),
                        TextEntry::make('child_sequence')
                            ->label('Position in Siblings')
                            ->suffix(fn ($state) => match ($state) {
                                1 => 'st Child',
                                2 => 'nd Child',
                                3 => 'rd Child',
                                default => 'th Child'
                            }),
                        IconEntry::make('is_married')
                            ->label('Married')
                            ->boolean()
                            ->visible(fn ($record) => (($record->gender->value ?? $record->gender) === \App\Enums\Gender::FEMALE->value)),
                        TextEntry::make('married_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->is_married && (($record->gender->value ?? $record->gender) === \App\Enums\Gender::FEMALE->value)),

                        TextEntry::make('zone.name')
                            ->label('Zone')
                            ->badge('success'),

                        TextEntry::make('deceased.zone.coordinator.name')
                            ->label('Coordinator')
                            ->badge('primary'),

                        TextEntry::make('address')
                            ->columnSpanFull(),
                    ])->columns(4),

                Section::make('ID Card Overview')
                    ->icon('heroicon-m-identification')
                    ->schema([
                        TextEntry::make('latest_id_card_number')
                            ->label('Card Number')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->idCards()->latest()->first()?->card_number ?? 'No ID Card Issued')
                            ->badge(),

                        TextEntry::make('latest_id_card_status')
                            ->label('Card Status')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => ucfirst($record->idCards()->latest()->first()?->status ?? 'Not Issued'))
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
                            ->getStateUsing(function (\App\Models\Orphan $record): string {
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
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->idCards()->latest()->first()?->issued_at)
                            ->date('d M, Y')
                            ->placeholder('N/A'),

                        TextEntry::make('latest_id_card_expires_at')
                            ->label('Expires At')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->idCards()->latest()->first()?->expires_at)
                            ->date('d M, Y')
                            ->placeholder('N/A'),
                    ])->columns(3),

                Section::make('Sponsorship Overview')
                    ->icon('heroicon-m-heart')
                    ->schema([
                        TextEntry::make('sponsorship_status')
                            ->label('Sponsorship Status')
                            ->getStateUsing(fn (\App\Models\Orphan $record): string => $record->hasActiveSponsorship() ? 'Active' : 'Not Sponsored')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray'),

                        TextEntry::make('active_sponsor_name')
                            ->label('Sponsor Name')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->activeSponsorships()->first()?->sponsor_name ?? '—')
                            ->visible(fn (\App\Models\Orphan $record) => $record->hasActiveSponsorship()),

                        TextEntry::make('active_sponsor_type')
                            ->label('Sponsor Category')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->activeSponsorships()->first()?->sponsor?->type?->getLabel() ?? '—')
                            ->badge()
                            ->visible(fn (\App\Models\Orphan $record) => $record->hasActiveSponsorship()),

                        TextEntry::make('active_sponsor_amount')
                            ->label('Amount Committed')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->activeSponsorships()->first()?->amount_committed)
                            ->money('NGN')
                            ->visible(fn (\App\Models\Orphan $record) => $record->hasActiveSponsorship()),

                        TextEntry::make('active_sponsor_start')
                            ->label('Effective Start')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->activeSponsorships()->first()?->start_date)
                            ->date('d M, Y')
                            ->visible(fn (\App\Models\Orphan $record) => $record->hasActiveSponsorship()),

                        TextEntry::make('active_sponsor_end')
                            ->label('Expiry Date')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->activeSponsorships()->first()?->end_date ? $record->activeSponsorships()->first()?->end_date->format('d M, Y') : 'Ongoing')
                            ->visible(fn (\App\Models\Orphan $record) => $record->hasActiveSponsorship()),

                        TextEntry::make('active_sponsor_notes')
                            ->label('Terms / Purpose')
                            ->getStateUsing(fn (\App\Models\Orphan $record) => $record->activeSponsorships()->first()?->notes)
                            ->visible(fn (\App\Models\Orphan $record) => $record->hasActiveSponsorship() && ! empty($record->activeSponsorships()->first()?->notes))
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('Historical Sponsorships')
                    ->icon('heroicon-m-clock')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (\App\Models\Orphan $record) => $record->sponsorships()->whereDate('end_date', '<', now()->startOfDay())->exists())
                    ->schema([
                        TextEntry::make('historical_sponsorship_records')
                            ->label('Past Sponsorship Records')
                            ->getStateUsing(function (\App\Models\Orphan $record): string {
                                $past = $record->sponsorships()->whereDate('end_date', '<', now()->startOfDay())->get();
                                if ($past->isEmpty()) {
                                    return 'No past sponsorships recorded.';
                                }

                                return $past->map(fn ($s) => "• {$s->sponsor_name} — ₦".number_format((float) $s->amount_committed, 2)." ({$s->start_date?->format('d/m/Y')} to {$s->end_date?->format('d/m/Y')})")->implode("\n");
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
