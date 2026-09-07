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
            ->passwordReset()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->profile()
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Security / MFA')
                    ->icon('heroicon-o-shield-check')
                    ->url('/mfa/settings'),
            ])
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->globalSearch()
            ->spa(hasPrefetching: true)
            ->sidebarCollapsibleOnDesktop()
            ->brandName('Garko Orphans Foundation (MIS)')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\Reports\PrescriptionReport::class,
                \App\Filament\Pages\StockAvailability::class,
                \App\Filament\Pages\ConsolidatedFinancialReport::class,
            ])
            ->authGuard('web')
            ->resources([
                EducationVerificationResource::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureActiveUser::class,
                \App\Http\Middleware\EnsureMfaVerified::class,
            ])
            ->renderHook(
                'panels::body.start',
                fn () => auth()->user()?->isDemoObserver() ? view('filament.components.demo-mode-banner') : ''
            )
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

                // Finance (admin + super-admin + report access)
                $hasFinanceGroupAccess = $user?->can('view_finances')
                    || $user?->can('finance.consolidated_report.view')
                    || $user?->isAdmin()
                    || $user?->isSuperAdmin()
                    || $user?->isDemoObserver();

                if ($hasFinanceGroupAccess) {
                    $financeItems = [];

                    if ($user?->can('view_finances') || $user?->isAdmin() || $user?->isSuperAdmin()) {
                        $financeItems[] = NavigationItem::make('Bank Accounts')
                            ->icon('heroicon-o-document-currency-dollar')
                            ->url('/admin/bank-accounts')
                            ->isActiveWhen(fn () => request()->is('admin/bank-accounts*'));

                        $financeItems[] = NavigationItem::make('Transactions')
                            ->icon('heroicon-o-document-text')
                            ->url('/admin/transactions')
                            ->isActiveWhen(fn () => request()->is('admin/transactions*'));
                    }

                    if (\App\Filament\Resources\OutOfPocketExpenditures\OutOfPocketExpenditureResource::canAccess()) {
                        $financeItems[] = NavigationItem::make('Out of Pocket Expenditures')
                            ->icon('heroicon-o-receipt-percent')
                            ->url('/admin/out-of-pocket-expenditures')
                            ->isActiveWhen(fn () => request()->is('admin/out-of-pocket-expenditures*'));
                    }

                    if (\App\Filament\Pages\ConsolidatedFinancialReport::canAccess()) {
                        $financeItems[] = NavigationItem::make('Consolidated Financial Report')
                            ->icon('heroicon-o-document-chart-bar')
                            ->url('/admin/consolidated-financial-report')
                            ->isActiveWhen(fn () => request()->is('admin/consolidated-financial-report*'));
                    }

                    if (! empty($financeItems)) {
                        $builder = $builder->group(
                            NavigationGroup::make('Finance')
                                ->items($financeItems)
                        );
                    }
                }

                // Beneficiary Registration Module (admin + super-admin)
                $hasDeceasedAccess = $user?->hasRole('super_admin') || $user?->can('view_deceased');
                $hasWidowAccess = $user?->hasRole('super_admin') || $user?->can('view_widows') || $user?->can('view_deceased');
                $hasOrphanAccess = $user?->hasRole('super_admin') || $user?->can('view_orphans') || $user?->can('view_deceased');

                if ($hasDeceasedAccess || $hasWidowAccess || $hasOrphanAccess) {
                    $items = [];

                    if ($hasDeceasedAccess) {
                        $items[] = NavigationItem::make('Deceased')
                            ->icon('heroicon-o-user-minus')
                            ->url('/admin/deceaseds')
                            ->isActiveWhen(fn () => request()->is('admin/deceaseds*'));
                    }

                    if ($hasWidowAccess) {
                        $items[] = NavigationItem::make('Widows')
                            ->icon('heroicon-o-heart')
                            ->url('/admin/widows')
                            ->isActiveWhen(fn () => request()->is('admin/widows*'));
                    }

                    if ($hasOrphanAccess) {
                        $items[] = NavigationItem::make('Orphans')
                            ->icon('heroicon-o-user-group')
                            ->url('/admin/orphans')
                            ->isActiveWhen(fn () => request()->is('admin/orphans*'));
                    }

                    if ($hasWidowAccess) {
                        $items[] = NavigationItem::make('Widow History')
                            ->icon('heroicon-o-clock')
                            ->url('/admin/widow-histories')
                            ->badge(fn () => (string) \App\Models\Widow::historical()->count(), color: 'gray')
                            ->isActiveWhen(fn () => request()->is('admin/widow-histories*'));
                    }

                    if ($hasOrphanAccess) {
                        $items[] = NavigationItem::make('Orphan History')
                            ->icon('heroicon-o-archive-box')
                            ->url('/admin/orphan-histories')
                            ->badge(fn () => (string) \App\Models\Orphan::historical()->count(), color: 'gray')
                            ->isActiveWhen(fn () => request()->is('admin/orphan-histories*'));
                    }

                    if ($hasDeceasedAccess) {
                        $items[] = NavigationItem::make('Zone Transfers')
                            ->icon('heroicon-o-arrows-right-left')
                            ->url('/admin/zone-transfers')
                            ->isActiveWhen(fn () => request()->is('admin/zone-transfers*'));
                    }

                    if (! empty($items)) {
                        $builder = $builder->group(
                            NavigationGroup::make('Beneficiary Registration')
                                ->items($items)
                        );
                    }
                }

                // Education Module (admin + super-admin + verifier)
                $hasEducationAccess = $user?->can('view_education_interventions');
                $hasAnalyticsAccess = $user?->can('orphan_education.analytics.view');

                if ($hasEducationAccess || $hasAnalyticsAccess) {
                    $educationItems = [];

                    if ($hasEducationAccess) {
                        $educationItems[] = NavigationItem::make('Institution')
                            ->icon('heroicon-o-building-library')
                            ->url('/admin/institutions')
                            ->isActiveWhen(fn () => request()->is('admin/institutions*'));

                        $educationItems[] = NavigationItem::make('Orphan Classes')
                            ->icon('heroicon-o-building-office')
                            ->url('/admin/orphan-classes')
                            ->isActiveWhen(fn () => request()->is('admin/orphan-classes*'));

                        $educationItems[] = NavigationItem::make('Orphan Education')
                            ->icon('heroicon-o-academic-cap')
                            ->url('/admin/orphan-education')
                            ->isActiveWhen(fn () => request()->is('admin/orphan-education*'));

                        $educationItems[] = NavigationItem::make('Vocational Skills')
                            ->icon('heroicon-o-presentation-chart-line')
                            ->url('/admin/vocational-skills')
                            ->isActiveWhen(fn () => request()->is('admin/vocational-skills*'));

                        $educationItems[] = NavigationItem::make('Education Fee Invoices')
                            ->icon('heroicon-o-banknotes')
                            ->url('/admin/education-fee-invoices')
                            ->isActiveWhen(fn () => request()->is('admin/education-fee-invoices*'));
                    }

                    if ($hasAnalyticsAccess) {
                        $educationItems[] = NavigationItem::make('Education Analytics')
                            ->icon('heroicon-o-chart-bar')
                            ->url('/admin/education-analytics')
                            ->isActiveWhen(fn () => request()->is('admin/education-analytics*'));
                    }

                    if (! empty($educationItems)) {
                        $builder = $builder->group(
                            NavigationGroup::make('Education')
                                ->items($educationItems)
                        );
                    }
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
                                    ->icon('heroicon-o-queue-list')
                                    ->url('/admin/items')
                                    ->isActiveWhen(fn () => request()->is('admin/items*')),

                                NavigationItem::make('Stock Availability')
                                    ->icon('heroicon-o-chart-bar')
                                    ->url('/admin/stock-availability')
                                    ->isActiveWhen(fn () => request()->is('admin/stock-availability*')),
                            ])
                    );
                }

                // ID Cards (admin + super-admin)
                if ($user?->can('view_id_cards')) {
                    $builder = $builder->group(
                        NavigationGroup::make('ID Cards')
                            ->items([

                                NavigationItem::make('ID Cards')
                                    ->icon('heroicon-o-identification')
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

                // Reports (admin + super-admin)
                if ($user?->can('view_medicals') || $user?->isAdmin() || $user?->isSuperAdmin()) {
                    $builder = $builder->group(
                        NavigationGroup::make('Reports')
                            ->items([
                                NavigationItem::make('Healthcare Reports')
                                    ->icon('heroicon-o-document-chart-bar')
                                    ->url('/admin/reports/prescription-report')
                                    ->isActiveWhen(fn () => request()->is('admin/reports/prescription-report*')),
                                NavigationItem::make('Project Report')
                                    ->icon('heroicon-o-building-office')
                                    ->url('/admin/reports/project-report')
                                    ->isActiveWhen(fn () => request()->is('admin/reports/project-report*')),
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
                if ($user?->isSuperAdmin() || $user?->isAdmin() || $user?->isDemoObserver()) {
                    $builder = $builder->group(
                        NavigationGroup::make('Security')
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

                                NavigationItem::make('MFA Management')
                                    ->icon('heroicon-o-shield-check')
                                    ->url('/admin/mfa-management')
                                    ->isActiveWhen(fn () => request()->is('admin/mfa-management*')),
                            ])
                    );
                }

                return $builder;
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\BankOverviewStatsWidget::class,
                \App\Filament\Widgets\StatsOverviewWidget::class,
                \App\Filament\Widgets\LoanRepaymentStatsWidget::class,
                \App\Filament\Widgets\FinancialOverviewWidget::class,
                \App\Filament\Widgets\GenderDistributionWidget::class,
                \App\Filament\Widgets\AgeDistributionChartWidget::class,
                \App\Filament\Widgets\IdCardPrintQueueWidget::class,
                \App\Filament\Widgets\LoanRepaymentWidget::class,
                \App\Filament\Widgets\LoanBeneficiariesWidget::class,
                \App\Filament\Widgets\EducationOverviewStatsWidget::class,
                \App\Filament\Widgets\EducationInterventionWidget::class,
                \App\Filament\Widgets\HealthcareBeneficiariesStatsWidget::class,
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
