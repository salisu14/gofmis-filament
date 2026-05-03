<?php
// app/Providers/Filament/CoordinatorPanelProvider.php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureCoordinator;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CoordinatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('coordinator')
            ->path('coordinator')
            ->login()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->spa(hasPrefetching: true)
            ->brandName('Coordinator Portal - Garko Foundation')
            ->brandLogo(asset('storage/logos/gof_logo.jpeg'))
            ->brandLogoHeight('3rem')
            //            ->brandLogo(fn () => view('filament.resources.brand', [
//                'title' => 'Coordinator Portal',
//            ]))
            ->favicon(asset('images/favicon.ico'))
            ->discoverResources(
                in: app_path('Filament/Coordinator/Resources'),
                for: 'App\\Filament\\Coordinator\\Resources'
            )
            // Only show coordinator-specific resources
            ->resources([
                \App\Filament\Coordinator\Resources\DeceasedResource::class,
                \App\Filament\Coordinator\Resources\OrphanResource::class,
                \App\Filament\Coordinator\Resources\WidowResource::class,
                \App\Filament\Coordinator\Resources\LoanRequestResource::class,
                \App\Filament\Coordinator\Resources\EducationRequestResource::class,
                \App\Filament\Coordinator\Resources\HealthcareRequestResource::class,
                \App\Filament\Coordinator\Resources\ProjectResource::class,
            ])

            ->pages([
                Pages\Dashboard::class,
            ])

            ->widgets([
                \App\Filament\Coordinator\Widgets\ZoneStatsWidget::class,         // sort 1 - full width
                \App\Filament\Coordinator\Widgets\QuickActionsWidget::class,      // sort 3 - 2 cols
                \App\Filament\Coordinator\Widgets\RecentActivityWidget::class,      // sort 4 - 2 cols
                \App\Filament\Coordinator\Widgets\LoanBeneficiariesWidget::class, // sort 2 - full width
                \App\Filament\Coordinator\Widgets\PendingItemsWidget::class,        // sort 5 - 2 cols
                \App\Filament\Coordinator\Widgets\ProjectOverviewWidget::class,        // sort 6 - full width

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
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureCoordinator::class, // Custom middleware
            ])

            ->navigationGroups([
                'Beneficiary Registration',
                'Requests & Interventions',
                'Projects',
            ])

            ->sidebarCollapsibleOnDesktop()
            ->spa();
    }
}
