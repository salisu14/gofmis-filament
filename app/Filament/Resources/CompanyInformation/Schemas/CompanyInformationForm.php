<?php

namespace App\Filament\Resources\CompanyInformation\Schemas;

use App\Services\Company\CompanyInformationService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;

class CompanyInformationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branding')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('logo_path')
                                    ->label('Logo')
                                    ->disk('public')
                                    ->directory('company/logos')
                                    ->image()
                                    ->maxSize(2048)
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                    ->downloadable()
                                    ->openable()
                                    ->helperText('Used on printable documents. Max 2MB. JPG, PNG, WebP, or SVG.')
                                    ->saveUploadedFileUsing(function (UploadedFile $file): string {
                                        return app(CompanyInformationService::class)->storeLogo($file);
                                    }),

                                FileUpload::make('favicon_path')
                                    ->label('Favicon')
                                    ->disk('public')
                                    ->image()
                                    ->directory('company/favicons')
                                    ->maxSize(512)
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon'])
                                    ->downloadable()
                                    ->openable()
                                    ->helperText('Max 512KB. ICO, PNG, or SVG.')
                                    ->saveUploadedFileUsing(function (UploadedFile $file): string {
                                        return app(CompanyInformationService::class)->storeFavicon($file);
                                    }),
                            ]),
                    ]),

                Section::make('Company Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('trading_name')
                            ->label('Trading Name')
                            ->maxLength(255)
                            ->placeholder('Leave blank to use company name'),

                        TextInput::make('registration_no')
                            ->label('Registration Number')
                            ->maxLength(100),

                        TextInput::make('tax_registration_no')
                            ->label('Tax Registration Number')
                            ->maxLength(100),

                        TextInput::make('phone_no')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('mobile_no')
                            ->label('Mobile Number')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://example.com'),
                    ]),

                Section::make('Address')
                    ->schema([
                        TextInput::make('address_line_1')
                            ->label('Address Line 1')
                            ->maxLength(255),

                        TextInput::make('address_line_2')
                            ->label('Address Line 2')
                            ->maxLength(255),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('city')
                                    ->label('City')
                                    ->maxLength(100),

                                TextInput::make('state_province')
                                    ->label('State / Province')
                                    ->maxLength(100),

                                TextInput::make('postal_code')
                                    ->label('Postal Code')
                                    ->maxLength(20),
                            ]),

                        TextInput::make('country_code')
                            ->label('Country Code')
                            ->maxLength(3)
                            ->placeholder('e.g. NGA'),
                    ]),

                Section::make('Bank Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255),

                        TextInput::make('bank_account_no')
                            ->label('Account Number')
                            ->maxLength(100),

                        TextInput::make('bank_branch')
                            ->label('Branch')
                            ->maxLength(255),

                        TextInput::make('swift_code')
                            ->label('SWIFT Code')
                            ->maxLength(50),
                    ]),

                Section::make('Contact Person')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_person_name')
                            ->label('Name')
                            ->maxLength(255),

                        TextInput::make('contact_person_title')
                            ->label('Title')
                            ->maxLength(100),

                        TextInput::make('contact_person_phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('contact_person_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
