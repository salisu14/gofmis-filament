<?php

namespace App\Console\Commands;

use App\Models\IdCard;
use App\Models\Orphan;
use App\Models\Widow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileIdCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'id-cards:reconcile {--details : Show granular beneficiary and card details}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a read-only audit of historical ID cards, reporting duplicates, status inconsistencies, and expired cards.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('           GOF MIS HISTORICAL ID CARD AUDIT REPORT          ');
        $this->info('============================================================');

        $totalCards = IdCard::count();
        $this->line("Total ID Cards Registered: {$totalCards}");

        $orphanCards = IdCard::where('cardable_type', Orphan::class)->count();
        $widowCards = IdCard::where('cardable_type', Widow::class)->count();
        $this->line("  - Orphan Cards: {$orphanCards}");
        $this->line("  - Widow Cards:  {$widowCards}");

        $this->newLine();
        $this->info('--- 1. CARD NUMBER DUPLICATION AUDIT ---');
        $duplicateNumbers = DB::table('id_cards')
            ->select('card_number', DB::raw('COUNT(*) as card_count'))
            ->groupBy('card_number')
            ->having('card_count', '>', 1)
            ->get();

        if ($duplicateNumbers->isEmpty()) {
            $this->info('[OK] Zero duplicate card numbers found.');
        } else {
            $this->warn('[ALERT] Found '.$duplicateNumbers->count().' duplicate card numbers:');
            foreach ($duplicateNumbers as $dup) {
                $this->line("  - Number: {$dup->card_number} (Count: {$dup->card_count})");
            }
        }

        $this->newLine();
        $this->info('--- 2. BENEFICIARY MULTIPLE CARDS AUDIT ---');
        $multiCardBeneficiaries = DB::table('id_cards')
            ->select('cardable_type', 'cardable_id', DB::raw('COUNT(*) as total_cards'))
            ->groupBy('cardable_type', 'cardable_id')
            ->having('total_cards', '>', 1)
            ->get();

        if ($multiCardBeneficiaries->isEmpty()) {
            $this->info('[OK] Zero beneficiaries with multiple total cards.');
        } else {
            $this->warn('[ALERT] Found '.$multiCardBeneficiaries->count().' beneficiaries with multiple total cards.');
            if ($this->option('details')) {
                foreach ($multiCardBeneficiaries as $ben) {
                    $type = str_replace('App\\Models\\', '', $ben->cardable_type);
                    $this->line("  - {$type} ID: {$ben->cardable_id} (Total Cards: {$ben->total_cards})");
                }
            }
        }

        $this->newLine();
        $this->info('--- 3. MULTIPLE OPEN CARDS (DRAFT / ACTIVE) AUDIT ---');
        $multiOpenBeneficiaries = DB::table('id_cards')
            ->select('cardable_type', 'cardable_id', DB::raw('COUNT(*) as open_cards'))
            ->whereIn('status', ['draft', 'active'])
            ->groupBy('cardable_type', 'cardable_id')
            ->having('open_cards', '>', 1)
            ->get();

        if ($multiOpenBeneficiaries->isEmpty()) {
            $this->info('[OK] Zero beneficiaries with multiple concurrent open (draft/active) cards.');
        } else {
            $this->error('[CRITICAL] Found '.$multiOpenBeneficiaries->count().' beneficiaries with multiple open cards!');
            if ($this->option('details')) {
                foreach ($multiOpenBeneficiaries as $ben) {
                    $type = str_replace('App\\Models\\', '', $ben->cardable_type);
                    $this->line("  - {$type} ID: {$ben->cardable_id} (Open Cards: {$ben->open_cards})");
                }
            }
        }

        $this->newLine();
        $this->info('--- 4. ORPHANED / MISSING RELATIONSHIP CARDS AUDIT ---');
        $missingBeneficiaryCount = 0;
        $missingTemplateCount = 0;

        foreach (IdCard::with(['cardable', 'template'])->get() as $card) {
            if (! $card->cardable) {
                $missingBeneficiaryCount++;
                if ($this->option('details')) {
                    $this->warn("  - Card {$card->card_number} is missing beneficiary ({$card->cardable_type} #{$card->cardable_id})");
                }
            }
            if (! $card->template) {
                $missingTemplateCount++;
                if ($this->option('details')) {
                    $this->warn("  - Card {$card->card_number} is missing template (#{$card->template_id})");
                }
            }
        }

        if ($missingBeneficiaryCount === 0) {
            $this->info('[OK] All cards resolve their beneficiary relationship.');
        } else {
            $this->warn("[ALERT] {$missingBeneficiaryCount} cards have missing beneficiary models.");
        }

        if ($missingTemplateCount === 0) {
            $this->info('[OK] All cards resolve their template relationship.');
        } else {
            $this->warn("[ALERT] {$missingTemplateCount} cards have missing template models.");
        }

        $this->newLine();
        $this->info('--- 5. CARD STATUS & EXPIRATION BREAKDOWN ---');
        $statusBreakdown = DB::table('id_cards')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->all();

        foreach ($statusBreakdown as $status => $count) {
            $this->line("  - Status '".($status ?: 'null')."': {$count}");
        }

        $expiredActiveCount = IdCard::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        if ($expiredActiveCount > 0) {
            $this->warn("  - Active but Expired Cards: {$expiredActiveCount}");
        } else {
            $this->info('[OK] Zero active expired cards found.');
        }

        $this->newLine();
        $this->info('============================================================');
        $this->info('AUDIT COMPLETE: Read-only reconciliation completed cleanly.');
        $this->info('============================================================');

        return Command::SUCCESS;
    }
}
