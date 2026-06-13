<?php

namespace App\Filament\Resources\IdCards\Schemas;

use App\Models\IdCard;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IdCardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Card Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('card_number')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'draft' => 'gray',
                                'active' => 'success',
                                'revoked' => 'danger',
                                'expired' => 'warning',
                            }),
                        TextEntry::make('template.name'),
                    ]),

                Section::make('Beneficiary Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cardable.full_name')
                            ->label('Name'),
                        TextEntry::make('cardable.nin')
                            ->label('NIN')
                            ->formatStateUsing(fn(?string $state) => $state ? substr($state, -4) : 'N/A'),
                        TextEntry::make('cardable.reg_no')
                            ->label('Reg. No'),
                        TextEntry::make('cardable.gender')
                            ->label('Gender'),
                        TextEntry::make('cardable.zone.name')
                            ->label('Zone'),
                    ]),

                Section::make('Validity')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('issued_at')
                            ->dateTime('F j, Y'),
                        TextEntry::make('expires_at')
                            ->dateTime('F j, Y'),
                        TextEntry::make('printed_at')
                            ->dateTime('F j, Y')
                            ->placeholder('Not printed yet'),
                    ]),

                Section::make('QR Code')
                    ->schema([
                        ImageEntry::make('qr_code_path')
                            ->disk('public')
                            ->imageHeight(200)
                            ->extraAttributes(['class' => 'rounded-lg border']),

                        TextEntry::make('qr_url')
                            ->label('Verification URL')
                            ->formatStateUsing(function (IdCard $record) {
                                $url = app(\App\Services\QRCodeService::class)->generateVerificationUrl($record);
                                return $url;
                            })
                            ->copyable(),
                    ]),
            ]);
    }
}
