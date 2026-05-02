<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create'),
                    ])->columns(2),

                Section::make('Identity & Roles')
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()->options(function () {
                                $user = auth()->user();

                                // Super-admin can assign any role
                                if ($user?->hasRole('super_admin')) {
                                    return \App\Models\Role::pluck('name', 'uuid');
                                }

                                // Admin cannot assign super-admin or manage roles
                                return \App\Models\Role::where('name', '!=', 'super_admin')
                                    ->pluck('name', 'uuid');
                            })
                            ->disabled(fn() => !auth()->user()?->can('assign roles')),

                    ])->columns(2),
            ]);
    }
}
