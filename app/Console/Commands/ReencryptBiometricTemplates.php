<?php

namespace App\Console\Commands;

use App\Models\BeneficiaryFingerprint;
use App\Services\Biometrics\BiometricTemplateCipher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrate legacy (APP_KEY-encrypted) biometric templates to the dedicated
 * biometric encryption key envelope.
 *
 * The command is safe to run repeatedly: rows already using the current
 * biometric:v envelope are left untouched. It never prints templates or
 * ciphertexts, works transactionally per record, and reports failures without
 * destroying existing ciphertext.
 */
class ReencryptBiometricTemplates extends Command
{
    protected $signature = 'biometrics:reencrypt-templates
        {--dry-run : Report how many rows would be migrated without changing anything}';

    protected $description = 'Re-encrypt legacy (APP_KEY) biometric templates with the dedicated biometric encryption key.';

    public function handle(BiometricTemplateCipher $cipher): int
    {
        $this->line('===================================================');
        $this->line('   BIOMETRIC TEMPLATE RE-ENCRYPTION');
        $this->line('===================================================');

        if (! $cipher->isKeyAvailable()) {
            $this->error('BIOMETRICS_ENCRYPTION_KEY is not configured or is invalid.');
            $this->line('Set a valid dedicated biometric key before running this command.');
            $this->error('Nothing was changed.');

            return self::FAILURE;
        }

        $total = BeneficiaryFingerprint::count();
        $current = BeneficiaryFingerprint::all()
            ->filter(fn (BeneficiaryFingerprint $print) => $print->usesCurrentEnvelope())
            ->count();
        $legacy = $total - $current;

        $this->line("Total fingerprint records         : {$total}");
        $this->line("Already using dedicated key       : {$current}");
        $this->line("Legacy (APP_KEY) records pending  : {$legacy}");

        if ($legacy === 0) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would re-encrypt {$legacy} record(s). No changes were made.");

            return self::SUCCESS;
        }

        $migrated = 0;
        $failed = 0;

        foreach (BeneficiaryFingerprint::all() as $print) {
            if ($print->usesCurrentEnvelope()) {
                continue;
            }

            try {
                DB::transaction(function () use ($print, &$migrated) {
                    if ($print->reencryptTemplate()) {
                        $migrated++;
                    }
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Failed to migrate fingerprint [{$print->getKey()}] (idempotent skip); original ciphertext preserved.");
            }
        }

        $this->newLine();
        $this->line("Migrated records : {$migrated}");
        $this->line("Failed records   : {$failed}");
        $this->line('No template or ciphertext content has been printed.');

        if ($failed > 0) {
            $this->error('One or more records could not be migrated. See warnings above.');

            return self::FAILURE;
        }

        $this->info('Biometric template re-encryption complete.');

        return self::SUCCESS;
    }
}
