<?php

namespace App\Jobs\Imprest;

use App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReplenishmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $replenishmentId) {}

    public function handle(ImprestReplenishmentServiceInterface $service): void
    {
        $service->process($this->replenishmentId);
    }
}
