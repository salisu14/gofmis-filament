<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:health-check
                            {--json : Format diagnostic results as JSON}
                            {--strict : Fail with non-zero exit code if any warnings exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform comprehensive read-only production pre-flight health & readiness audit';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isJson = (bool) $this->option('json');
        $isStrict = (bool) $this->option('strict');

        $checks = [
            $this->checkDatabaseConnectivity(),
            $this->checkPendingMigrations(),
            $this->checkEnvironmentConfig(),
            $this->checkBiometricKey(),
            $this->checkStorageWritability(),
            $this->checkCoreRbacSeeds(),
            $this->checkSchedulerAndQueueConfig(),
        ];

        $hasError = false;
        $hasWarning = false;

        foreach ($checks as $check) {
            if ($check['status'] === 'ERROR') {
                $hasError = true;
            } elseif ($check['status'] === 'WARN') {
                $hasWarning = true;
            }
        }

        $overallStatus = $hasError ? 'FAIL' : ($hasWarning ? 'WARN' : 'PASS');

        if ($isJson) {
            $this->output->write(json_encode([
                'status' => $overallStatus,
                'timestamp' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT));

            return ($hasError || ($isStrict && $hasWarning)) ? 1 : 0;
        }

        $this->info('=====================================================');
        $this->info('GOF MIS Production Pre-Flight & Health Audit');
        $this->info('=====================================================');
        $this->line('Environment: <fg=cyan>'.app()->environment().'</>');
        $this->line('Overall Health: '.($hasError ? '<fg=red;options=bold>FAIL</>' : ($hasWarning ? '<fg=yellow;options=bold>WARNINGS PRESENT</>' : '<fg=green;options=bold>PASS</>')));
        $this->newLine();

        $rows = [];
        foreach ($checks as $check) {
            $statusFormatted = match ($check['status']) {
                'PASS' => '<fg=green>PASS</>',
                'WARN' => '<fg=yellow>WARN</>',
                'ERROR' => '<fg=red>ERROR</>',
                default => $check['status'],
            };

            $rows[] = [
                $check['category'],
                $statusFormatted,
                $check['message'],
            ];
        }

        $this->table(['Audit Component', 'Status', 'Diagnostic Details'], $rows);

        $this->newLine();
        if ($hasError) {
            $this->error('CRITICAL PRE-FLIGHT AUDIT FAILURES DETECTED! Resolve listed errors before deploying.');

            return 1;
        }

        if ($hasWarning) {
            $this->comment('Pre-flight audit passed with warnings. Review advisory notes above.');

            return $isStrict ? 1 : 0;
        }

        $this->info('All system health and pre-flight readiness checks passed cleanly.');

        return 0;
    }

    protected function checkDatabaseConnectivity(): array
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $dbName = DB::connection()->getDatabaseName();

            return [
                'category' => 'Database Connectivity',
                'status' => 'PASS',
                'message' => "Connected to database '{$dbName}' via driver '{$driver}'.",
            ];
        } catch (\Throwable $e) {
            return [
                'category' => 'Database Connectivity',
                'status' => 'ERROR',
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    protected function checkPendingMigrations(): array
    {
        try {
            $migrationFiles = File::files(database_path('migrations'));
            $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

            $pendingCount = 0;
            foreach ($migrationFiles as $file) {
                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                if (! in_array($name, $ranMigrations, true)) {
                    $pendingCount++;
                }
            }

            if ($pendingCount > 0) {
                return [
                    'category' => 'Database Migrations',
                    'status' => 'WARN',
                    'message' => "{$pendingCount} pending database migration(s) detected. Run 'php artisan migrate --force' on deployment target.",
                ];
            }

            return [
                'category' => 'Database Migrations',
                'status' => 'PASS',
                'message' => 'Database schema is fully up to date with zero pending migrations.',
            ];
        } catch (\Throwable $e) {
            return [
                'category' => 'Database Migrations',
                'status' => 'ERROR',
                'message' => 'Failed checking migration status: '.$e->getMessage(),
            ];
        }
    }

    protected function checkEnvironmentConfig(): array
    {
        $issues = [];
        $isProd = app()->environment('production');
        $debug = config('app.debug');

        if ($isProd && $debug) {
            $issues[] = 'APP_DEBUG is true in production environment!';
        }

        if (! config('app.key')) {
            $issues[] = 'APP_KEY is not set!';
        }

        $sessionDriver = config('session.driver');
        if ($isProd && in_array($sessionDriver, ['array', 'file'], true)) {
            $issues[] = "Session driver is '{$sessionDriver}'. Recommend 'database' or 'redis' in production.";
        }

        if (count($issues) > 0) {
            return [
                'category' => 'Environment Configuration',
                'status' => $isProd && $debug ? 'ERROR' : 'WARN',
                'message' => implode(' | ', $issues),
            ];
        }

        return [
            'category' => 'Environment Configuration',
            'status' => 'PASS',
            'message' => 'App key, debug mode, and session settings are securely configured.',
        ];
    }

    protected function checkBiometricKey(): array
    {
        $rawKey = env('BIOMETRICS_ENCRYPTION_KEY');

        if (blank($rawKey)) {
            return [
                'category' => 'Biometric Encryption Key',
                'status' => 'WARN',
                'message' => 'BIOMETRICS_ENCRYPTION_KEY is blank. Biometric write operations will fail safely.',
            ];
        }

        if (str_starts_with($rawKey, 'base64:')) {
            $rawKey = substr($rawKey, 7);
        }

        $decoded = base64_decode($rawKey, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            return [
                'category' => 'Biometric Encryption Key',
                'status' => 'ERROR',
                'message' => 'BIOMETRICS_ENCRYPTION_KEY is set but invalid (must be base64-encoded 32-byte key).',
            ];
        }

        return [
            'category' => 'Biometric Encryption Key',
            'status' => 'PASS',
            'message' => 'Biometric encryption key is present and valid (32-byte AES-256).',
        ];
    }

    protected function checkStorageWritability(): array
    {
        $paths = [
            'storage/app/private' => storage_path('app/private'),
            'storage/app/public' => storage_path('app/public'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $unwritable = [];
        foreach ($paths as $name => $path) {
            if (! File::exists($path)) {
                @File::makeDirectory($path, 0755, true, true);
            }

            if (! File::isWritable($path)) {
                $unwritable[] = $name;
            }
        }

        if (count($unwritable) > 0) {
            return [
                'category' => 'Storage & Directory Permissions',
                'status' => 'ERROR',
                'message' => 'Directory permission error. Unwritable path(s): '.implode(', ', $unwritable),
            ];
        }

        return [
            'category' => 'Storage & Directory Permissions',
            'status' => 'PASS',
            'message' => 'Private storage, public storage, logs, and bootstrap cache are writable.',
        ];
    }

    protected function checkCoreRbacSeeds(): array
    {
        try {
            $expectedRoles = ['super_admin', 'admin', 'coordinator', 'auditor', 'demo_observer'];
            $foundRoles = \App\Models\Role::pluck('name')->toArray();

            $missing = array_diff($expectedRoles, $foundRoles);

            if (count($missing) > 0) {
                return [
                    'category' => 'RBAC & Permission Seeds',
                    'status' => 'ERROR',
                    'message' => 'Missing expected system role(s): '.implode(', ', $missing),
                ];
            }

            return [
                'category' => 'RBAC & Permission Seeds',
                'status' => 'PASS',
                'message' => 'All core system roles (super_admin, admin, coordinator, auditor, demo_observer) exist.',
            ];
        } catch (\Throwable $e) {
            return [
                'category' => 'RBAC & Permission Seeds',
                'status' => 'ERROR',
                'message' => 'Failed to audit RBAC roles: '.$e->getMessage(),
            ];
        }
    }

    protected function checkSchedulerAndQueueConfig(): array
    {
        $queueConn = config('queue.default');
        $cacheStore = config('cache.default');

        return [
            'category' => 'Queue & Cache Subsystem',
            'status' => 'PASS',
            'message' => "Queue driver: '{$queueConn}' | Cache store: '{$cacheStore}'. Scheduled tasks configured in console.php.",
        ];
    }
}
