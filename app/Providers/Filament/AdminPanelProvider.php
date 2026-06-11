<?php

// app/Providers/Filament/AdminPanelProvider.php

namespace App\Providers\Filament;

use App\Filament\Resources\Verifications\EducationVerificationResource;
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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
            ->databaseNotifications()
            ->globalSearch()
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->brandName('Garko Orphans Foundation (MIS)')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->authGuard('web')
            ->resources([
                EducationVerificationResource::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                $user = auth()->user();

                // Dashboard (all authenticated users)
                $builder = $builder
                    ->items([
                        NavigationItem::make('Dashboard')
                            ->icon('heroicon-o-home')
                            ->url('/admin')
                            ->isActiveWhen(fn () => request()->is('admin')),
                    ]);

                // Finance (admin + super-admin)
                if ($user?->can('view_finances')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Finance')
                            ->items([
                                NavigationItem::make('Bank Accounts')
                                    ->icon('heroicon-o-document-currency-dollar')
                                    ->url('/admin/bank-accounts')
                                    ->isActiveWhen(fn () => request()->is('admin/bank-accounts*')),

                                NavigationItem::make('Transactions')
                                    ->icon('heroicon-o-document-text')
                                    ->url('/admin/transactions')
                                    ->isActiveWhen(fn () => request()->is('admin/transactions*')),
                            ])
                    );
                }

                // Deceased Module (admin + super-admin)
                if ($user?->can('view_deceased')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Deceased')
                            ->items([
                                NavigationItem::make('Deceased')
                                    ->icon('heroicon-o-user-minus')
                                    ->url('/admin/deceaseds')
                                    ->isActiveWhen(fn () => request()->is('admin/deceaseds*')),

                                NavigationItem::make('Widows')
                                    ->icon('heroicon-o-heart')
                                    ->url('/admin/widows')
                                    ->isActiveWhen(fn () => request()->is('admin/widows*')),

                                NavigationItem::make('Orphans')
                                    ->icon('heroicon-o-user-group')
                                    ->url('/admin/orphans')
                                    ->isActiveWhen(fn () => request()->is('admin/orphans*')),

                                NavigationItem::make('Zone Transfers')
                                    ->icon('heroicon-o-arrows-right-left')
                                    ->url('/admin/zone-transfers')
                                    ->isActiveWhen(fn () => request()->is('admin/zone-transfers*')),
                            ])
                    );
                }

                // Education Module (admin + super-admin + verifier)
                if ($user?->can('view_education_interventions')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Education')
                            ->items([
                                NavigationItem::make('Institution')
                                    ->icon('heroicon-o-building-library')
                                    ->url('/admin/institutions')
                                    ->isActiveWhen(fn () => request()->is('admin/institutions*')),

                                NavigationItem::make('Orphan Classes')
                                    ->icon('heroicon-o-building-office')
                                    ->url('/admin/orphan-classes')
                                    ->isActiveWhen(fn () => request()->is('admin/orphan-classes*')),

                                NavigationItem::make('Orphan Education')
                                    ->icon('heroicon-o-academic-cap')
                                    ->url('/admin/orphan-education')
                                    ->isActiveWhen(fn () => request()->is('admin/orphan-education*')),

                                NavigationItem::make('Vocational Skills')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/vocational-skills')
                                    ->isActiveWhen(fn () => request()->is('admin/vocational-skills*')),

                                NavigationItem::make('Education Fee Invoices')
                                    ->icon('heroicon-o-banknotes')
                                    ->url('/admin/education-fee-invoices')
                                    ->isActiveWhen(fn () => request()->is('admin/education-fee-invoices*')),
                            ])
                    );
                }

                // Interventions (admin + super-admin)
                if ($user?->can('view_interventions')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Interventions')
                            ->items([
                                NavigationItem::make('Categories')
                                    ->icon('heroicon-o-tag')
                                    ->url('/admin/categories')
                                    ->isActiveWhen(fn () => request()->is('admin/categories*')),

                                NavigationItem::make('Intervention Types')
                                    ->icon('heroicon-o-presentation-chart-line')
                                    ->url('/admin/intervention-types')
                                    ->isActiveWhen(fn () => request()->is('admin/intervention-types*')),

                                NavigationItem::make('Intervention Requests')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->url('/admin/intervention-requests')
                                    ->isActiveWhen(fn () => request()->is('admin/intervention-requests*')),

                                NavigationItem::make('Welfare Packages')
                                    ->icon('heroicon-o-building-storefront')
                                    ->url('/admin/welfare-packages')
                                    ->isActiveWhen(fn () => request()->is('admin/welfare-packages*')),

                                NavigationItem::make('Items')
                                    ->icon('heroicon-o-building-storefront')
                                    ->url('/admin/items')
                                    ->isActiveWhen(fn () => request()->is('admin/items*')),
                            ])
                    );
                }

                // ID Cards (admin + super-admin)
                if ($user?->can('view_id_cards')) {
                    $builder = $builder->group(
                        NavigationGroup::make('ID Cards')
                            ->items([

                                NavigationItem::make('ID Cards')
                                    ->icon('heroicon-o-credit-card')
                                    ->url('/admin/id-cards')
                                    ->isActiveWhen(fn () => request()->is('admin/id-cards*')),

                                NavigationItem::make('ID Card Templates')
                                    ->icon('heroicon-o-circle-stack')
                                    ->url('/admin/id-card-templates')
                                    ->isActiveWhen(fn () => request()->is('admin/id-card-templates*')),

                                NavigationItem::make('ID Card Print Batches')
                                    ->icon('heroicon-o-printer')
                                    ->url('/admin/id-card-print-batches')
                                    ->isActiveWhen(fn () => request()->is('admin/id-card-print-batches*')),
                            ])
                    );
                }

                // Sponsorship & Projects (admin + super-admin)
                if ($user?->can('view_sponsorships')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Sponsorship & Projects')
                            ->items([
                                NavigationItem::make('Sponsors')
                                    ->icon('heroicon-o-trophy')
                                    ->url('/admin/donors')
                                    ->isActiveWhen(fn () => request()->is('admin/donors*')),

                                NavigationItem::make('Sponsorships')
                                    ->icon('heroicon-o-receipt-percent')
                                    ->url('/admin/sponsorships')
                                    ->isActiveWhen(fn () => request()->is('admin/sponsorships*')),

                                NavigationItem::make('Projects')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->url('/admin/projects')
                                    ->isActiveWhen(fn () => request()->is('admin/projects*')),
                            ])
                    );
                }

                // Revolving Loan (admin + super-admin)
                if ($user?->can('view_loans')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Revolving Loan')
                            ->items([
                                NavigationItem::make('Widow Loan')
                                    ->icon('heroicon-o-square-2-stack')
                                    ->url('/admin/widow-loans')
                                    ->isActiveWhen(fn () => request()->is('admin/widow-loans*')),

                                NavigationItem::make('Loan Repayment')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->url('/admin/widow-loan-repayments')
                                    ->isActiveWhen(fn () => request()->is('admin/widow-loan-repayments*')),
                            ])
                    );
                }

                // Medicals (admin + super-admin)
                if ($user?->can('view_medicals')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Medicals')
                            ->items([
                                NavigationItem::make('Prescriptions')
                                    ->icon('heroicon-o-paper-clip')
                                    ->url('/admin/prescriptions')
                                    ->isActiveWhen(fn () => request()->is('admin/prescriptions*')),

                                NavigationItem::make('Medications')
                                    ->icon('heroicon-o-viewfinder-circle')
                                    ->url('/admin/medications')
                                    ->isActiveWhen(fn () => request()->is('admin/medications*')),

                                NavigationItem::make('Common Illnesses')
                                    ->icon('heroicon-o-beaker')
                                    ->url('/admin/illnesses')
                                    ->isActiveWhen(fn () => request()->is('admin/illnesses*')),
                            ])
                    );
                }

                // Address (admin + super-admin)
                if ($user?->can('view_addresses')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Address')
                            ->items([
                                NavigationItem::make('States')
                                    ->icon('heroicon-o-list-bullet')
                                    ->url('/admin/states')
                                    ->isActiveWhen(fn () => request()->is('admin/states*')),

                                NavigationItem::make('Zones')
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->url('/admin/zones')
                                    ->isActiveWhen(fn () => request()->is('admin/zones*')),
                            ])
                    );
                }

                // Education Verification (admin + super-admin + verifier)
                if ($user?->can('verify_education_interventions')) {
                    $builder = $builder->group(
                        NavigationGroup::make('Education Verification')
                            ->items([
                                NavigationItem::make('Verify Requests')
                                    ->icon('heroicon-o-academic-cap')
                                    ->url('/admin/education-verification')
                                    ->isActiveWhen(fn () => request()->is('admin/education-verification*')),
                            ])
                    );
                }

                // Auth/Settings (super-admin ONLY)
                if (
                    $user?->can('view_users') ||
                    $user?->can('user_access') ||
                    $user?->can('view_roles') ||
                    $user?->can('role_access')
                ) {
                    $builder = $builder->group(
                        NavigationGroup::make('Auth')
                            ->collapsible()
                            ->items([
                                NavigationItem::make('Company Information')
                                    ->icon('heroicon-o-building-office-2')
                                    ->url('/admin/company-information'),

                                NavigationItem::make('Users')
                                    ->icon('heroicon-o-users')
                                    ->url('/admin/users'),

                                NavigationItem::make('Roles & Permissions')
                                    ->icon('heroicon-o-shield-check')
                                    ->url('/admin/roles'),
                            ])
                    );
                }

                return $builder;
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\LoanRepaymentStatsWidget::class,
                \App\Filament\Widgets\FinancialOverviewWidget::class,
                \App\Filament\Widgets\GenderDistributionWidget::class,
                \App\Filament\Widgets\AgeDistributionChartWidget::class,
                \App\Filament\Widgets\IdCardPrintQueueWidget::class,
                \App\Filament\Widgets\LoanRepaymentWidget::class,
                \App\Filament\Widgets\LoanBeneficiariesWidget::class,
                \App\Filament\Widgets\EducationInterventionWidget::class,
                \App\Filament\Widgets\HealthcareInterventionWidget::class,
                \App\Filament\Widgets\WelfareInterventionWidget::class,
                \App\Filament\Widgets\SpecialInterventionWidget::class,
                \App\Filament\Widgets\PendingApprovalsWidget::class,
                \App\Filament\Widgets\OverAgedOrphansWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                VerifyCsrfToken::class,
            ])
            ->navigationGroups([
                'Beneficiary Management',
                'ID Card Management',
                'Finance',
                'Settings',
            ]);
    }
}
