<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::policy(\App\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\App\Models\Permission::class, \App\Policies\PermissionPolicy::class);
        Gate::policy(\App\Models\Orphan::class, \App\Policies\OrphanPolicy::class);

        Gate::before(function ($user, $ability, $arguments = []) {
            // For security models (User, Role, Permission) and protected models (Orphan), ALWAYS fall back to policy to enforce invariants.
            $target = is_array($arguments) ? reset($arguments) : $arguments;
            if ($target) {
                $class = is_object($target) ? get_class($target) : (is_string($target) ? $target : null);
                if ($class && in_array($class, [\App\Models\User::class, \App\Models\Role::class, \App\Models\Permission::class, \App\Models\Orphan::class], true)) {
                    return null; // Fall back to policy
                }
            }

            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
