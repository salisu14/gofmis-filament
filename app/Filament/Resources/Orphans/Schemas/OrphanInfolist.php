<?php

namespace App\Filament\Resources\Orphans\Schemas;

use App\Models\Orphan;
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
                        ImageEntry::make('picture_url')
                            ->label('')
                            ->disk('public')
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
                            ->getStateUsing(fn ($record) =>
                            $record->birth_date ? Carbon::parse($record->birth_date)->age : null
                            )
                            ->suffix(' Years Old')
                    ])->columns(4),

                Section::make('Eligibility & Status')
                    ->schema([
                        IconEntry::make('is_eligible')
                            ->label('Eligible')
                            ->boolean(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'inactive' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('nin')
                            ->label('NIN')
                            ->placeholder('Not Provided'),
                        TextEntry::make('deceased.full_name')
                            ->label('Parent Record')
                            ->placeholder('Unknown'),
                        IconEntry::make('has_birth_cert')
                            ->label('Birth Certificate')
                            ->boolean(),
                        TextEntry::make('birth_certificate_path')
                            ->label('Certificate Link')
                            ->url(fn ($record) => $record->birth_certificate_path ? asset('storage/' . $record->birth_certificate_path) : null)
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
                            ->suffix(fn ($state) => match($state) { 1 => 'st Child', 2 => 'nd Child', 3 => 'rd Child', default => 'th Child' }),
                        IconEntry::make('is_married')
                            ->label('Married')
                            ->boolean(),
                        TextEntry::make('married_at')
                            ->dateTime()
                            ->visible(fn ($record) => $record->is_married),
                        TextEntry::make('address')
                            ->columnSpanFull(),
                    ])->columns(4),
            ]);
    }
}
