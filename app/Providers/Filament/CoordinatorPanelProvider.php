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
            ->profile()
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Security / MFA')
                    ->icon('heroicon-o-shield-check')
                    ->url('/mfa/settings'),
            ])
            ->brandName('Coordinator Portal - Garko Foundation')
            ->favicon(asset('images/favicon.ico'))
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString('
                    <style>
                        /* Coordinator Panel Scoped Styling System */
                        .coordinator-dashboard {
                            display: flex;
                            flex-direction: column;
                            gap: 1rem;
                        }

                        /* Quick Actions Grid & Cards */
                        .coordinator-quick-actions-grid {
                            display: grid;
                            grid-template-columns: repeat(1, minmax(0, 1fr));
                            gap: 0.875rem;
                        }

                        @media (min-width: 640px) {
                            .coordinator-quick-actions-grid {
                                grid-template-columns: repeat(2, minmax(0, 1fr));
                            }
                        }

                        @media (min-width: 1024px) {
                            .coordinator-quick-actions-grid {
                                grid-template-columns: repeat(4, minmax(0, 1fr));
                            }
                        }

                        .coordinator-quick-action-card {
                            display: flex;
                            align-items: center;
                            gap: 0.75rem;
                            padding: 0.875rem 1rem;
                            border-radius: 0.75rem;
                            background-color: #ffffff;
                            border: 1px solid #e5e7eb;
                            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                            transition: all 0.15s ease-in-out;
                            text-decoration: none !important;
                            cursor: pointer;
                        }

                        .dark .coordinator-quick-action-card {
                            background-color: #1f2937;
                            border-color: #374151;
                        }

                        .coordinator-quick-action-card:hover {
                            transform: translateY(-2px);
                            border-color: #10b981;
                            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1), 0 2px 4px -1px rgba(16, 185, 129, 0.06);
                        }

                        .dark .coordinator-quick-action-card:hover {
                            border-color: #34d399;
                        }

                        /* Icon Wrappers with Deterministic Bounds */
                        .coordinator-action-icon-wrapper {
                            width: 2.5rem !important;
                            height: 2.5rem !important;
                            min-width: 2.5rem !important;
                            min-height: 2.5rem !important;
                            flex: 0 0 2.5rem !important;
                            border-radius: 0.5rem;
                            display: flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                        }

                        .coordinator-action-icon-wrapper svg,
                        svg.coordinator-action-icon {
                            width: 1.25rem !important;
                            height: 1.25rem !important;
                            min-width: 1.25rem !important;
                            min-height: 1.25rem !important;
                            max-width: 1.25rem !important;
                            max-height: 1.25rem !important;
                            flex: none !important;
                        }

                        .coordinator-widget-icon-sm {
                            width: 2rem !important;
                            height: 2rem !important;
                            min-width: 2rem !important;
                            min-height: 2rem !important;
                            flex: 0 0 2rem !important;
                            border-radius: 9999px;
                            display: flex !important;
                            align-items: center !important;
                            justify-content: center !important;
                        }

                        .coordinator-widget-icon-sm svg,
                        svg.coordinator-widget-icon-sm {
                            width: 1rem !important;
                            height: 1rem !important;
                            min-width: 1rem !important;
                            min-height: 1rem !important;
                            max-width: 1rem !important;
                            max-height: 1rem !important;
                            flex: none !important;
                        }

                        /* Pending Item Cards Grid */
                        .coordinator-pending-tiles-grid {
                            display: grid;
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                            gap: 0.75rem;
                            margin-bottom: 0.75rem;
                        }

                        @media (min-width: 640px) {
                            .coordinator-pending-tiles-grid {
                                grid-template-columns: repeat(4, minmax(0, 1fr));
                            }
                        }

                        .coordinator-pending-tile {
                            padding: 0.75rem;
                            border-radius: 0.5rem;
                            border-width: 1px;
                            display: flex;
                            flex-direction: column;
                            gap: 0.375rem;
                        }

                        /* Activity Rows with Subtle Dividers */
                        .coordinator-activity-row {
                            display: flex;
                            align-items: center;
                            gap: 0.75rem;
                            padding: 0.625rem 0.5rem;
                            border-bottom: 1px solid #f3f4f6;
                            transition: background-color 0.15s ease;
                            border-radius: 0.375rem;
                            text-decoration: none !important;
                        }

                        .dark .coordinator-activity-row {
                            border-bottom-color: #374151;
                        }

                        .coordinator-activity-row:last-child {
                            border-bottom: none;
                        }

                        .coordinator-activity-row:hover {
                            background-color: #f9fafb;
                        }

                        .dark .coordinator-activity-row:hover {
                            background-color: #1f2937;
                        }

                        /* Filament StatsOverviewWidget Stat Icons in Coordinator Panel */
                        .fi-wi-stats-overview-stat-icon,
                        .fi-wi-stats-overview-stat-icon svg,
                        .fi-wi-stats-overview-stat-description-icon,
                        .fi-wi-stats-overview-stat-description-icon svg,
                        .fi-wi-stats-overview-stat svg {
                            width: 1.5rem !important;
                            height: 1.5rem !important;
                            min-width: 1.5rem !important;
                            min-height: 1.5rem !important;
                            max-width: 1.5rem !important;
                            max-height: 1.5rem !important;
                            flex: none !important;
                        }
                    </style>
                ')
            )
            ->discoverResources(
                in: app_path('Filament/Coordinator/Resources'),
                for: 'App\\Filament\\Coordinator\\Resources'
            )
            // Only show coordinator-specific resources
            ->resources([
                \App\Filament\Coordinator\Resources\DeceasedResource::class,
                \App\Filament\Coordinator\Resources\WidowResource::class,
                \App\Filament\Coordinator\Resources\OrphanResource::class,
                \App\Filament\Coordinator\Resources\WidowHistoryResource::class,
                \App\Filament\Coordinator\Resources\OrphanHistoryResource::class,
                \App\Filament\Coordinator\Resources\EducationRequestResource::class,
                \App\Filament\Coordinator\Resources\WelfareRequestResource::class,
            ])

            ->pages([
                Pages\Dashboard::class,
            ])

            ->widgets([
                \App\Filament\Coordinator\Widgets\ZoneStatsWidget::class,         // sort 1 - full width
                \App\Filament\Coordinator\Widgets\QuickActionsWidget::class,      // sort 2 - full width
                \App\Filament\Coordinator\Widgets\RecentActivityWidget::class,    // sort 3 - 1 col
                \App\Filament\Coordinator\Widgets\PendingItemsWidget::class,      // sort 4 - 1 col
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
                \App\Http\Middleware\EnsureActiveUser::class,
                \App\Http\Middleware\EnsureMfaVerified::class,
                EnsureCoordinator::class, // Custom middleware
            ])

            ->navigationGroups([
                'Beneficiaries',
                'Intervention Requests',
            ])

            ->sidebarCollapsibleOnDesktop()
            ->spa();
    }
}
