<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ─────────────────────────────────────────
                // ACCOUNT & SECURITY
                // ─────────────────────────────────────────
                Section::make('Account & Security')
                    ->description('Core login credentials and account status.')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])
                            ->schema([
                                // Avatar column
                                FileUpload::make('photo')
                                    ->label('Profile Photo')
                                    ->avatar()
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([null, '1:1'])
                                    ->directory('users/avatars')
                                    ->maxSize(2048)
                                    ->columnSpan(1),

                                // Credentials column
                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Full Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->autocomplete('name'),

                                        TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->autocomplete('email')
                                            ->prefixIcon('heroicon-o-envelope'),

                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('password')
                                                    ->label(fn (string $context): string => $context === 'create' ? 'Password' : 'New Password')
                                                    ->password()
                                                    ->revealable()
                                                    ->required(fn (string $context): bool => $context === 'create')
                                                    ->rule(Password::default()->mixedCase()->numbers()->symbols())
                                                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                                    ->dehydrated(fn ($state) => filled($state))
                                                    ->live(debounce: 500)
                                                    ->autocomplete('new-password'),

                                                TextInput::make('password_confirmation')
                                                    ->label('Confirm Password')
                                                    ->password()
                                                    ->revealable()
                                                    ->required(fn (string $context): bool => $context === 'create')
                                                    ->visible(fn ($get) => filled($get('password')))
                                                    ->same('password')
                                                    ->dehydrated(false)
                                                    ->autocomplete('new-password'),
                                            ]),

                                        Toggle::make('is_active')
                                            ->label('Account Active')
                                            ->default(true)
                                            ->onIcon('heroicon-o-check')
                                            ->offIcon('heroicon-o-x-mark')
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->helperText('Inactive users cannot log in.'),
                                    ])
                                    ->columnSpan(['lg' => 2]),
                            ]),
                    ]),

                // ─────────────────────────────────────────
                // PERSONAL INFORMATION
                // ─────────────────────────────────────────
                Section::make('Personal Information')
                    ->description('Demographics and contact details.')
                    ->icon('heroicon-o-user')
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                    ->schema([
                        TextInput::make('phone')
                            ->label('Primary Phone')
                            ->tel()
                            ->prefixIcon('heroicon-o-phone')
                            ->maxLength(20)
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/'),

                        TextInput::make('alternate_phone')
                            ->label('Alternate Phone')
                            ->tel()
                            ->prefixIcon('heroicon-o-device-phone-mobile')
                            ->maxLength(20)
                            ->telRegex('/^[+]*[(]{0,1}[0-9]{1,4}[)]{0,1}[-\s\.\/0-9]*$/'),

                        TextInput::make('designation')
                            ->label('Designation / Title')
                            ->maxLength(50)
                            ->placeholder('e.g. Senior Coordinator'),

                        Select::make('gender')
                            ->label('Gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                                'prefer_not_to_say' => 'Prefer not to say',
                            ])
                            ->native(false)
                            ->placeholder('Select gender'),

                        DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->subYears(13))
                            ->placeholder('DD/MM/YYYY')
                            ->helperText('Must be at least 13 years old.'),

                        TextInput::make('address')
                            ->label('Address')
                            ->maxLength(255)
                            ->placeholder('Street address, city, state, zip')
                            ->columnSpanFull(),
                    ]),

                // ─────────────────────────────────────────
                // ROLES & PERMISSIONS
                // ─────────────────────────────────────────
                Section::make('Roles & Permissions')
                    ->description('Define user access level. Coordinators must have the coordinator role assigned here.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Select::make('roles')
                            ->label('Assigned Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(function () {
                                $user = auth()->user();

                                if ($user?->hasRole('super_admin')) {
                                    return \App\Models\Role::pluck('name', 'uuid');
                                }

                                return \App\Models\Role::where('name', '!=', 'super_admin')
                                    ->pluck('name', 'uuid');
                            })
                            ->disabled(function (): bool {
                                $user = auth()->user();
                                return ! ($user?->can('assign_roles') || $user?->can('role_edit'));
                            })
                            ->helperText(function () {
                                $user = auth()->user();
                                if ($user?->hasRole('super_admin')) {
                                    return 'You can assign any role including Super Admin.';
                                }
                                return 'Super Admin role is restricted.';
                            })
                            ->placeholder('Select roles'),
                    ]),
            ]);
    }
}
