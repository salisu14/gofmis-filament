<?php

namespace App\Console\Commands;

use App\Services\WidowLoanDelinquencyService;
use Illuminate\Console\Command;

class EvaluateWidowLoanDelinquency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'widow-loans:evaluate-delinquency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate delinquency metrics and update performance status for outstanding widow loans';

    /**
     * Execute the console command.
     */
    public function handle(WidowLoanDelinquencyService $service): int
    {
        $this->info('Starting widow loan delinquency evaluation...');

        $result = $service->evaluateAllEligibleLoans();

        $this->info("Completed evaluation. Processed {$result['processed_count']} outstanding loans.");

        return Command::SUCCESS;
    }
}
