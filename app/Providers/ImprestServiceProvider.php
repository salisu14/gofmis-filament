<?php

namespace App\Providers;

use App\Events\Imprest\TransactionApproved;
use App\Events\Imprest\TransactionCreated;
use App\Events\Imprest\TransactionVoided;
use App\Listeners\Imprest\CheckMissingReceipts;
use App\Listeners\Imprest\LogTransactionActivity;
use App\Listeners\Imprest\NotifyLowBalance;
use App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestReconciliationRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface;
use App\Repositories\Imprest\ImprestFundRepository;
use App\Repositories\Imprest\ImprestReconciliationRepository;
use App\Repositories\Imprest\ImprestTransactionRepository;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface;
use App\Services\Contracts\Imprest\ImprestTransactionServiceInterface;
use App\Services\Imprest\ImprestReconciliationService;
use App\Services\Imprest\ImprestReplenishmentService;
use App\Services\Imprest\ImprestTransactionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ImprestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ImprestTransactionRepositoryInterface::class, ImprestTransactionRepository::class);
        $this->app->bind(ImprestFundRepositoryInterface::class, ImprestFundRepository::class);
        $this->app->bind(ImprestReconciliationRepositoryInterface::class, ImprestReconciliationRepository::class);

        $this->app->bind(ImprestTransactionServiceInterface::class, ImprestTransactionService::class);
        $this->app->bind(ImprestReconciliationServiceInterface::class, ImprestReconciliationService::class);
        $this->app->bind(ImprestReplenishmentServiceInterface::class, ImprestReplenishmentService::class);
    }

    public function boot(): void
    {
        Event::listen(TransactionCreated::class, LogTransactionActivity::class);
        Event::listen(TransactionCreated::class, CheckMissingReceipts::class);
        Event::listen(TransactionApproved::class, LogTransactionActivity::class);
        Event::listen(TransactionApproved::class, NotifyLowBalance::class);
        Event::listen(TransactionVoided::class, LogTransactionActivity::class);
    }
}
