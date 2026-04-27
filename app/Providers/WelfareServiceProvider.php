<?php

namespace App\Providers;

use App\Events\BeneficiaryCollected;
use App\Listeners\LogBeneficiaryCollection;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class WelfareServiceProvider extends ServiceProvider
{
    protected $listen = [
        BeneficiaryCollected::class => [
            LogBeneficiaryCollection::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
