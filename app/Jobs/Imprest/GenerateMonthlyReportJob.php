<?php

namespace App\Jobs\Imprest;

use App\Models\ImprestFund;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $fundId,
        public readonly string $month,
    ) {}

    public function handle(ImprestReconciliationServiceInterface $service): void
    {
        $fund = ImprestFund::find($this->fundId);
        if (!$fund) return;

        $start = now()->parse($this->month)->startOfMonth()->toDateString();
        $end = now()->parse($this->month)->endOfMonth()->toDateString();

        $report = $service->getReconciliationReport($this->fundId, $start, $end);

        // Store report or dispatch notification
        // Report::create([...]);
    }
}
