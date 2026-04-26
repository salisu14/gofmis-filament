<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // In register() method:
        $this->app->bind(
            \App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface::class,
            \App\Repositories\Imprest\ImprestTransactionRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface::class,
            \App\Repositories\Imprest\ImprestFundRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\Imprest\ImprestReconciliationRepositoryInterface::class,
            \App\Repositories\Imprest\ImprestReconciliationRepository::class
        );

        $this->app->bind(
            \App\Services\Contracts\Imprest\ImprestTransactionServiceInterface::class,
            \App\Services\Imprest\ImprestTransactionService::class
        );

        $this->app->bind(
            \App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface::class,
            \App\Services\Imprest\ImprestReconciliationService::class
        );

        $this->app->bind(
            \App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface::class,
            \App\Services\Imprest\ImprestReplenishmentService::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\Imprest\ImprestReplenishmentRepositoryInterface::class,
            \App\Repositories\Imprest\ImprestReplenishmentRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
