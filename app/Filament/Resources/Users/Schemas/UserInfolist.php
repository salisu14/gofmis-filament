<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Overview')
                    ->schema([
                        ImageEntry::make('photo')
                            ->label('Profile Photo')
                            ->circular()
                            ->defaultImageUrl(fn ($record) => "https://ui-avatars.com/api/?name=" . urlencode($record->name) . "&color=FFFFFF&background=09090b"),
                        TextEntry::make('name')->weight('bold'),
                        TextEntry::make('email')->label('Email address')->copyable()->icon('heroicon-o-envelope'),
                        TextEntry::make('email_verified_at')
                            ->label('Verified At')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Not Verified')
                            ->color(fn ($state) => $state ? 'success' : 'danger'),
                        IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                    ])->columns(3),

                Section::make('Personal Details')
                    ->schema([
                        TextEntry::make('phone')->label('Primary Phone')->icon('heroicon-o-phone')->placeholder('—'),
                        TextEntry::make('alternate_phone')->icon('heroicon-o-device-phone-mobile')->placeholder('—'),
                        TextEntry::make('designation')->placeholder('—')->badge(),
                        TextEntry::make('gender')->placeholder('—')->badge()->color('info'),
                        TextEntry::make('date_of_birth')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                    ])->columns(3),

                Section::make('Roles & Assignment')
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'super_admin' => 'danger',
                                'admin' => 'warning',
                                'coordinator' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('coordinatedZone.name')
                            ->label('Coordinated Zone')
                            ->placeholder('No zone assigned')
                            ->badge()
                            ->color('info'),
                    ])->columns(2),

                Section::make('Audit Trail')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('updated_at')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ])->columns(2),
            ]);
    }
}
