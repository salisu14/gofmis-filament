<?php

namespace App\Filament\Resources\CompanyInformation\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInformationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Foundation Identity')
                    ->schema([
                        TextEntry::make('company_name')
                            ->label('Foundation Name')
                            ->weight('bold'),
                        TextEntry::make('trading_name')
                            ->label('Trading / Document Name')
                            ->placeholder('Uses foundation name'),
                        TextEntry::make('registration_no')
                            ->label('NGO Registration No.')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('tax_registration_no')
                            ->label('TIN')
                            ->copyable()
                            ->placeholder('-'),
                    ])->columns(4),

                Section::make('Address & Contact')
                    ->schema([
                        TextEntry::make('address_line_1')
                            ->placeholder('-'),
                        TextEntry::make('address_line_2')
                            ->placeholder('-'),
                        TextEntry::make('city')
                            ->placeholder('-'),
                        TextEntry::make('state_province')
                            ->label('State')
                            ->placeholder('-'),
                        TextEntry::make('postal_code')
                            ->placeholder('-'),
                        TextEntry::make('country_code')
                            ->label('Country'),
//                            ->formatStateUsing(fn (?string $state): string => CountryCode::tryFrom((string) $state)?->label() ?? (string) $state),
                        TextEntry::make('phone_no')
                            ->placeholder('-')
                            ->copyable()
                            ->icon('heroicon-o-phone'),
                        TextEntry::make('mobile_no')
                            ->placeholder('-')
                            ->copyable()
                            ->icon('heroicon-o-device-phone-mobile'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-')
                            ->copyable()
                            ->icon('heroicon-o-envelope')
                            ->url(fn(?string $state): ?string => $state ? "mailto:{$state}" : null),
                        TextEntry::make('website')
                            ->placeholder('-')
                            ->icon('heroicon-o-globe-alt')
                            ->url(fn(?string $state): ?string => $state ? (str_starts_with($state, 'http') ? $state : "https://{$state}") : null)
                            ->openUrlInNewTab(),
                    ])->columns(3),

                Section::make('Primary Contact')
                    ->schema([
                        TextEntry::make('contact_person_name')
                            ->placeholder('-'),
                        TextEntry::make('contact_person_title')
                            ->placeholder('-'),
                        TextEntry::make('contact_person_phone')
                            ->placeholder('-'),
                        TextEntry::make('contact_person_email')
                            ->placeholder('-')
                            ->url(fn(?string $state): ?string => $state ? "mailto:{$state}" : null),
                    ])->columns(2),

                Section::make('Branding & Banking')
                    ->schema([
                        ImageEntry::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->imageHeight(64),
                        ImageEntry::make('favicon_path')
                            ->label('Favicon')
                            ->disk('public')
                            ->imageHeight(24),
                        TextEntry::make('bank_name')
                            ->placeholder('-'),
                        TextEntry::make('bank_account_no')
                            ->placeholder('-'),
                        TextEntry::make('bank_branch')
                            ->placeholder('-'),
                        TextEntry::make('swift_code')
                            ->placeholder('-'),
                    ])->columns(2),
            ]);
    }
}
