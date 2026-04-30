<?php

namespace App\Providers\Filament;

use App\Filament\Imprest\Pages\Dashboard;
use App\Filament\Imprest\Resources\ImprestFundResource;
use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use App\Filament\Imprest\Resources\ImprestReconciliationResource;
use App\Http\Middleware\Imprest\EnsureFundCustodian;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ImprestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('imprest')
            ->path('imprest')
            ->login()
            ->colors([
                'primary' => Color::Emerald,
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Teal,
            ])
            ->brandName('GOF - Impress Management')
            ->brandLogo(fn () => view('filament.imprest.logo'))
            ->favicon(asset('favicon.ico'))
            ->discoverResources(in: app_path('Filament/Imprest/Resources'), for: 'App\\Filament\\Imprest\\Resources')
            ->discoverPages(in: app_path('Filament/Imprest/Pages'), for: 'App\\Filament\\Imprest\\Pages')
            ->discoverWidgets(in: app_path('Filament/Imprest/Widgets'), for: 'App\\Filament\\Imprest\\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Imprest\Widgets\FundOverviewWidget::class,
                \App\Filament\Imprest\Widgets\PendingTransactionsWidget::class,
                \App\Filament\Imprest\Widgets\RecentActivityWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureFundCustodian::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                'Fund Management',
                'Transactions',
                'Audit & Reconciliation',
                'Reports',
            ])
            ->spa()
            ->databaseTransactions()
            ->sidebarCollapsibleOnDesktop();
    }
}
