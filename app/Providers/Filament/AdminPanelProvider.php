<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
//use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->spa(hasPrefetching: true)
            ->brandName('Garko Orphans')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])  ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    // Dashboard
                    ->items([
                        NavigationItem::make('Dashboard')
                            ->icon('heroicon-o-home')
                            ->url('/admin')
                            ->isActiveWhen(fn() => request()->is('admin')),
                    ])
                    // Deceased Module
                    ->group(
                        NavigationGroup::make('Deceased')
                            ->items([
                                NavigationItem::make('Deceased')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/deceaseds')
                                    ->isActiveWhen(fn() => request()->is('admin/deceaseds*')),

                                // Sponsorships
                                NavigationItem::make('Sponsorships')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/sponsorships')
                                    ->isActiveWhen(fn() => request()->is('admin/sponsorhips*')),
                            ])
                    )
                    // Medicals
                    ->group(
                        NavigationGroup::make('Medicals')
                            ->items([
                                // Allocation
                                NavigationItem::make('Medications')
                                    ->icon('heroicon-o-viewfinder-circle')
                                    ->url('/admin/medications')
                                    ->isActiveWhen(fn() => request()->is('admin/medications*')),

                                // Prescriptions
                                NavigationItem::make('Prescriptions')
                                    ->icon('heroicon-o-paper-clip')
                                    ->url('/admin/prescriptions')
                                    ->isActiveWhen(fn() => request()->is('admin/prescriptions*')),

                            ])
                    )

                    // Interventions
                    ->group(
                        NavigationGroup::make('Interventions')
                            ->items([
                                // Categories
                                NavigationItem::make('Categories')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/categories')
                                    ->isActiveWhen(fn() => request()->is('admin/categories*')),

                                // Intervention Type
                                NavigationItem::make('Intervention Types')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/intervention-types')
                                    ->isActiveWhen(fn() => request()->is('admin/intervention-types*')),

                                // Intervention Request
                                NavigationItem::make('Intervention Requests')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/intervention-requests')
                                    ->isActiveWhen(fn() => request()->is('admin/intervention-requests*')),

                            ])
                    )

                    // Address Module
                    ->group(
                        NavigationGroup::make('Address')
                            ->items([
                                NavigationItem::make('States')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/states')
                                    ->isActiveWhen(fn() => request()->is('admin/states*')),

                                // Account Schedules
                                NavigationItem::make('Zones')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->url('/admin/zones')
                                    ->isActiveWhen(fn() => request()->is('admin/zones*')),


                                // Bank Accounts
                                NavigationItem::make('Bank Accounts')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/bank-accounts')
                                    ->isActiveWhen(fn() => request()->is('admin/bank-accounts*')),

                                // Payments
                                NavigationItem::make('Payments')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/payments')
                                    ->isActiveWhen(fn() => request()->is('admin/payments*')),
                            ])
                    )
                    ->group(
                        NavigationGroup::make('Education')
                            ->items([
                                NavigationItem::make('Institution')
                                    ->icon('heroicon-o-document-duplicate')
                                    ->url('/admin/institutions')
                                    ->isActiveWhen(fn() => request()->is('admin/institutions*')),

                                NavigationItem::make('Orphan Education')
                                    ->icon('heroicon-o-academic-cap')
                                    ->url('/admin/orphan-education')
                                    ->isActiveWhen(fn() => request()->is('admin/orphan-education*')),

                                NavigationItem::make('Vocational Skills')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/vocational-skills')
                                    ->isActiveWhen(fn() => request()->is('admin/vocational-skills*')),

                                NavigationItem::make('Education Fee Invoices')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/education-fee-invoices')
                                    ->isActiveWhen(fn() => request()->is('admin/education-fee-invoices*')),
                            ])
                    )
                    ->group(
                        NavigationGroup::make('Human Resources')
                            ->items([
                                NavigationItem::make('Business Units')
                                    ->icon('heroicon-o-wrench')
                                    ->url('/admin/businesses')
                                    ->isActiveWhen(fn() => request()->is('admin/businesses*')),

                                NavigationItem::make('Factories')
                                    ->icon('heroicon-o-building-storefront')
                                    ->url('/admin/factories')
                                    ->isActiveWhen(fn() => request()->is('admin/factories*')),

                                NavigationItem::make('Departments')
                                    ->icon('heroicon-o-building-office')
                                    ->url('/admin/departments')
                                    ->isActiveWhen(fn() => request()->is('admin/departments*')),

                                NavigationItem::make('Employees')
                                    ->icon('heroicon-o-user-group')
                                    ->url('/admin/employees')
                                    ->isActiveWhen(fn() => request()->is('admin/employees*')),

                                NavigationItem::make('Payroll Periods')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->url('/admin/payroll-periods')
                                    ->isActiveWhen(fn() => request()->is('admin/payroll-periods*')),

                                NavigationItem::make('Tax Tables')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/tax-tables')
                                    ->isActiveWhen(fn() => request()->is('admin/tax-tables*')),

                                NavigationItem::make('Social Security Tiers')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/social-security-tiers')
                                    ->isActiveWhen(fn() => request()->is('admin/social-security-tiers*')),

                                NavigationItem::make('Purchase Receipts')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/purchase-receipts')
                                    ->isActiveWhen(fn() => request()->is('admin/purchase-receipts*')),
                            ])
                    )

                    // Setup & Administration
                    ->group(
                        NavigationGroup::make('Auth')
                            ->collapsible()
                            ->items([
                                // Company Information
                                NavigationItem::make('Company Information')
                                    ->icon('heroicon-o-building-office-2')
                                    ->url('/admin/company-information'),

                                // Users & Permissions
                                NavigationItem::make('Users')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/users'),

                                NavigationItem::make('Roles & Permissions')
                                    ->icon('heroicon-o-shield-check')
                                    ->url('/admin/roles'),
                            ])
                    );
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
//                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                VerifyCsrfToken::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
