<?php

namespace App\Console\Commands;

use App\Models\Deceased;
use App\Models\Orphan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateBeneficiaryCertificatesToPrivateStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beneficiaries:migrate-certificates-private
                            {--apply : Execute actual migration of files from public to private storage disk}
                            {--cleanup-public : Remove public source file after verified migration to private disk}
                            {--json : Format output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely inventory and migrate legacy beneficiary certificates from public to private (local) storage disk';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isApply = (bool) $this->option('apply');
        $cleanupPublic = (bool) $this->option('cleanup-public');
        $isJson = (bool) $this->option('json');

        if (! $isJson) {
            $this->info('=====================================================');
            $this->info('Beneficiary Certificate Private Storage Migration Tool');
            $this->info('=====================================================');
            $this->line('Mode: '.($isApply ? '<fg=yellow>APPLY (MUTATING)</>' : '<fg=green>DRY-RUN (READ-ONLY)</>'));
            $this->line('Cleanup Public: '.($cleanupPublic ? '<fg=yellow>YES</>' : '<fg=gray>NO</>'));
            $this->newLine();
        }

        $results = [
            'mode' => $isApply ? 'apply' : 'dry-run',
            'cleanup_public' => $cleanupPublic,
            'orphans' => $this->processOrphans($isApply, $cleanupPublic),
            'deceased' => $this->processDeceased($isApply, $cleanupPublic),
            'unreferenced_public' => $this->scanUnreferencedPublicFiles(),
        ];

        $totalRecords = $results['orphans']['total'] + $results['deceased']['total'];
        $totalMigrated = $results['orphans']['migrated'] + $results['deceased']['migrated'];
        $totalWouldMigrate = $results['orphans']['would_migrate'] + $results['deceased']['would_migrate'];
        $totalAlreadyPrivate = $results['orphans']['already_private'] + $results['deceased']['already_private'];
        $totalConflicts = $results['orphans']['conflicts'] + $results['deceased']['conflicts'];
        $totalMissing = $results['orphans']['missing'] + $results['deceased']['missing'];

        if ($isJson) {
            $this->output->write(json_encode([
                'status' => 'success',
                'summary' => [
                    'total_records' => $totalRecords,
                    'already_private' => $totalAlreadyPrivate,
                    'migrated' => $totalMigrated,
                    'would_migrate' => $totalWouldMigrate,
                    'conflicts' => $totalConflicts,
                    'missing' => $totalMissing,
                    'unreferenced_public_files' => count($results['unreferenced_public']),
                ],
                'details' => $results,
            ], JSON_PRETTY_PRINT));

            return 0;
        }

        $this->table(
            ['Category', 'Total DB Records', 'Already Private', $isApply ? 'Migrated' : 'Would Migrate', 'Hash Conflicts', 'Missing Files'],
            [
                [
                    'Orphan Birth Certificates',
                    $results['orphans']['total'],
                    $results['orphans']['already_private'],
                    $isApply ? $results['orphans']['migrated'] : $results['orphans']['would_migrate'],
                    $results['orphans']['conflicts'],
                    $results['orphans']['missing'],
                ],
                [
                    'Deceased Death Certificates',
                    $results['deceased']['total'],
                    $results['deceased']['already_private'],
                    $isApply ? $results['deceased']['migrated'] : $results['deceased']['would_migrate'],
                    $results['deceased']['conflicts'],
                    $results['deceased']['missing'],
                ],
                [
                    'TOTALS',
                    $totalRecords,
                    $totalAlreadyPrivate,
                    $isApply ? $totalMigrated : $totalWouldMigrate,
                    $totalConflicts,
                    $totalMissing,
                ],
            ]
        );

        if (count($results['unreferenced_public']) > 0) {
            $this->newLine();
            $this->warn(sprintf('Found %d unreferenced certificate file(s) on public disk:', count($results['unreferenced_public'])));
            foreach ($results['unreferenced_public'] as $path) {
                $this->line('  - '.$path);
            }
        }

        $this->newLine();
        if (! $isApply) {
            $this->comment('This was a DRY-RUN. No files were copied or deleted.');
            $this->comment('To execute actual file migration, run with: php artisan beneficiaries:migrate-certificates-private --apply');
        } else {
            $this->info('Migration completed successfully.');
        }

        return 0;
    }

    /**
     * Process Orphan birth certificates.
     */
    protected function processOrphans(bool $isApply, bool $cleanupPublic): array
    {
        $stats = [
            'total' => 0,
            'already_private' => 0,
            'migrated' => 0,
            'would_migrate' => 0,
            'conflicts' => 0,
            'missing' => 0,
            'items' => [],
        ];

        $orphans = Orphan::whereNotNull('birth_certificate_path')
            ->where('birth_certificate_path', '!=', '')
            ->get();

        $stats['total'] = $orphans->count();

        foreach ($orphans as $orphan) {
            $path = $orphan->birth_certificate_path;
            $res = $this->migratePath($path, $isApply, $cleanupPublic);

            $stats['items'][] = [
                'id' => $orphan->id,
                'reg_no' => $orphan->reg_no,
                'path' => $path,
                'status' => $res['status'],
                'message' => $res['message'],
            ];

            if ($res['status'] === 'already_private') {
                $stats['already_private']++;
            } elseif ($res['status'] === 'migrated') {
                $stats['migrated']++;
            } elseif ($res['status'] === 'would_migrate') {
                $stats['would_migrate']++;
            } elseif ($res['status'] === 'conflict') {
                $stats['conflicts']++;
            } elseif ($res['status'] === 'missing') {
                $stats['missing']++;
            }
        }

        return $stats;
    }

    /**
     * Process Deceased death certificates.
     */
    protected function processDeceased(bool $isApply, bool $cleanupPublic): array
    {
        $stats = [
            'total' => 0,
            'already_private' => 0,
            'migrated' => 0,
            'would_migrate' => 0,
            'conflicts' => 0,
            'missing' => 0,
            'items' => [],
        ];

        $deceasedList = Deceased::whereNotNull('death_cert_url')
            ->where('death_cert_url', '!=', '')
            ->get();

        $stats['total'] = $deceasedList->count();

        foreach ($deceasedList as $deceased) {
            $path = $deceased->death_cert_url;
            $res = $this->migratePath($path, $isApply, $cleanupPublic);

            $stats['items'][] = [
                'id' => $deceased->id,
                'reg_no' => $deceased->reg_no,
                'path' => $path,
                'status' => $res['status'],
                'message' => $res['message'],
            ];

            if ($res['status'] === 'already_private') {
                $stats['already_private']++;
            } elseif ($res['status'] === 'migrated') {
                $stats['migrated']++;
            } elseif ($res['status'] === 'would_migrate') {
                $stats['would_migrate']++;
            } elseif ($res['status'] === 'conflict') {
                $stats['conflicts']++;
            } elseif ($res['status'] === 'missing') {
                $stats['missing']++;
            }
        }

        return $stats;
    }

    /**
     * Execute file migration logic for a single path.
     */
    protected function migratePath(string $path, bool $isApply, bool $cleanupPublic): array
    {
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return ['status' => 'conflict', 'message' => 'Invalid path structure or path traversal attempt blocked defensively.'];
        }

        $inPrivate = Storage::disk('local')->exists($path);
        $inPublic = Storage::disk('public')->exists($path);

        if ($inPrivate) {
            if ($inPublic && $cleanupPublic && $isApply) {
                // Verify hash match before deleting public copy
                $privateHash = hash('sha256', Storage::disk('local')->get($path));
                $publicHash = hash('sha256', Storage::disk('public')->get($path));

                if ($privateHash === $publicHash) {
                    Storage::disk('public')->delete($path);

                    return ['status' => 'already_private', 'message' => 'File exists privately; public copy cleaned up after hash match.'];
                }

                return ['status' => 'conflict', 'message' => 'Hash mismatch between private and public file. Public copy NOT deleted.'];
            }

            return ['status' => 'already_private', 'message' => 'File already exists in private storage.'];
        }

        if (! $inPublic) {
            return ['status' => 'missing', 'message' => 'File missing from both public and private storage.'];
        }

        // File exists on public disk but NOT on local disk
        if (! $isApply) {
            return ['status' => 'would_migrate', 'message' => 'File exists on public disk; candidate for migration.'];
        }

        // Apply mode: Copy public -> local
        $content = Storage::disk('public')->get($path);
        Storage::disk('local')->put($path, $content);

        // Verify copy integrity
        if (! Storage::disk('local')->exists($path)) {
            return ['status' => 'conflict', 'message' => 'Failed to verify written private file.'];
        }

        $publicHash = hash('sha256', $content);
        $privateHash = hash('sha256', Storage::disk('local')->get($path));

        if ($publicHash !== $privateHash) {
            Storage::disk('local')->delete($path);

            return ['status' => 'conflict', 'message' => 'Hash mismatch after writing private file; private copy discarded.'];
        }

        if ($cleanupPublic) {
            Storage::disk('public')->delete($path);
        }

        return ['status' => 'migrated', 'message' => 'File successfully migrated to private storage.'];
    }

    /**
     * Find files on public disk that are not referenced in DB records.
     */
    protected function scanUnreferencedPublicFiles(): array
    {
        $unreferenced = [];
        $directories = ['birth-certificates', 'death-certs'];

        $orphanPaths = Orphan::whereNotNull('birth_certificate_path')
            ->pluck('birth_certificate_path')
            ->filter()
            ->toArray();

        $deceasedPaths = Deceased::whereNotNull('death_cert_url')
            ->pluck('death_cert_url')
            ->filter()
            ->toArray();

        $referenced = array_flip(array_merge($orphanPaths, $deceasedPaths));

        foreach ($directories as $dir) {
            if (Storage::disk('public')->exists($dir)) {
                $files = Storage::disk('public')->allFiles($dir);
                foreach ($files as $file) {
                    if (! isset($referenced[$file])) {
                        $unreferenced[] = $file;
                    }
                }
            }
        }

        return $unreferenced;
    }
}
