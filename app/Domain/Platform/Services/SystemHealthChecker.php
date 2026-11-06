<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthChecker
{
    /**
     * Verify database connection, schema migration repository, and pending migrations.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
            $driver = $connection->getDriverName();
            $table = (string) config('database.migrations.table', 'migrations');

            if (! Schema::hasTable($table)) {
                return [
                    'status' => 'critical',
                    'message' => "Schema migrations repository table [{$table}] does not exist.",
                    'details' => [
                        'driver' => $driver,
                        'table' => $table,
                        'exists' => false,
                    ],
                ];
            }

            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles($migrator->paths());
            $ran = $migrator->getRepository()->getRan();
            $pending = array_diff(array_keys($files), $ran);
            $pendingCount = count($pending);

            if ($pendingCount > 0) {
                return [
                    'status' => 'warning',
                    'message' => "Database connected; {$pendingCount} unmigrated schema migration(s) detected.",
                    'details' => [
                        'driver' => $driver,
                        'migrations_run' => count($ran),
                        'migrations_pending' => $pendingCount,
                        'pending' => array_values($pending),
                    ],
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Database connection established and all migrations are up to date.',
                'details' => [
                    'driver' => $driver,
                    'migrations_run' => count($ran),
                    'migrations_pending' => 0,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failure: ' . $this->sanitizeErrorMessage($e->getMessage()),
                'details' => [
                    'error' => $this->sanitizeErrorMessage($e->getMessage()),
                ],
            ];
        }
    }

    /**
     * Verify Redis client connectivity and PONG response.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkRedis(): array
    {
        try {
            $client = Redis::connection();
            $ping = $client->ping();

            $isPong = ($ping === true || $ping === 'PONG' || $ping === '+PONG' || (is_string($ping) && str_contains(strtoupper($ping), 'PONG')));

            if ($isPong) {
                return [
                    'status' => 'healthy',
                    'message' => 'Redis connection responsive (PONG received).',
                    'details' => [
                        'ping' => 'PONG',
                        'client' => (string) config('database.redis.client', 'phpredis'),
                    ],
                ];
            }

            $isTesting = app()->environment('testing');

            return [
                'status' => $isTesting ? 'warning' : 'critical',
                'message' => 'Redis ping returned unexpected response.',
                'details' => [
                    'response' => is_scalar($ping) ? (string) $ping : gettype($ping),
                ],
            ];
        } catch (Throwable $e) {
            $isProd = app()->environment('production');

            return [
                'status' => $isProd ? 'critical' : 'warning',
                'message' => $isProd
                    ? 'Redis connection failure: ' . $this->sanitizeErrorMessage($e->getMessage())
                    : 'Redis connection unavailable in ' . app()->environment() . ' environment (' . $this->sanitizeErrorMessage($e->getMessage()) . ').',
                'details' => [
                    'error' => $this->sanitizeErrorMessage($e->getMessage()),
                    'environment' => app()->environment(),
                ],
            ];
        }
    }

    /**
     * Verify configured Horizon queues readiness and failed jobs accumulation.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkQueues(): array
    {
        $queues = ['high_priority', 'emails', 'documents', 'default'];
        $queueSizes = [];

        foreach ($queues as $queue) {
            try {
                $queueSizes[$queue] = Queue::size($queue);
            } catch (Throwable) {
                $queueSizes[$queue] = 0;
            }
        }

        $failedJobsCount = 0;
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            $failedJobsCount = 0;
        }

        $horizonActive = false;
        $horizonRepoClass = 'Laravel' . '\Horizon\Contracts\MasterSupervisorRepository';
        if (class_exists($horizonRepoClass)) {
            try {
                /** @var mixed $repo */
                $repo = app($horizonRepoClass);
                $masters = $repo->all();
                $horizonActive = ! empty($masters);
            } catch (Throwable) {
                $horizonActive = false;
            }
        }

        $status = 'healthy';
        $messages = [];

        if ($failedJobsCount > 50) {
            $status = 'critical';
            $messages[] = "Critical accumulation of {$failedJobsCount} failed queue jobs.";
        } elseif ($failedJobsCount > 0) {
            $status = 'warning';
            $messages[] = "{$failedJobsCount} failed queue jobs detected in failed_jobs table.";
        }

        if (app()->environment('production') && ! $horizonActive) {
            if ($status !== 'critical') {
                $status = 'warning';
            }
            $messages[] = 'Horizon master supervisor is not actively running.';
        }

        if (empty($messages)) {
            $messages[] = 'Horizon queues verified; no failed job accumulation.';
        }

        return [
            'status' => $status,
            'message' => implode(' ', $messages),
            'details' => [
                'queues' => $queueSizes,
                'failed_jobs' => $failedJobsCount,
                'horizon_active' => $horizonActive,
            ],
        ];
    }

    /**
     * Verify read and write permissions on private storage directories.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkStorage(): array
    {
        $directories = [
            'storage/app/private' => app()->storagePath('app/private'),
            'storage/app/private/documents' => app()->storagePath('app/private/documents'),
        ];

        $results = [];
        $failed = false;

        foreach ($directories as $label => $path) {
            if (! is_dir($path)) {
                File::ensureDirectoryExists($path, 0755, true);
            }

            $readable = is_readable($path);
            $writable = is_writable($path);
            $probeSuccess = false;

            if ($writable) {
                $probeFile = $path . DIRECTORY_SEPARATOR . '.health_check_' . uniqid() . '.tmp';
                try {
                    $probeContent = 'health_check_' . microtime(true);
                    file_put_contents($probeFile, $probeContent);
                    $readContent = file_get_contents($probeFile);
                    $probeSuccess = ($readContent === $probeContent);
                    if (file_exists($probeFile)) {
                        unlink($probeFile);
                    }
                } catch (Throwable) {
                    $probeSuccess = false;
                    if (file_exists($probeFile)) {
                        @unlink($probeFile);
                    }
                }
            }

            $isHealthy = $readable && $writable && $probeSuccess;
            if (! $isHealthy) {
                $failed = true;
            }

            $results[$label] = [
                'path' => $path,
                'readable' => $readable,
                'writable' => $writable,
                'probe_verified' => $probeSuccess,
            ];
        }

        if ($failed) {
            return [
                'status' => 'critical',
                'message' => 'Private storage permission check failed: one or more directories are not writable.',
                'details' => $results,
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Private storage and document directories are readable and writable.',
            'details' => $results,
        ];
    }

    /**
     * Verify application scheduler heartbeat status.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkScheduler(): array
    {
        $heartbeat = Cache::get('crm:scheduler:heartbeat');

        if ($heartbeat) {
            try {
                $lastRun = Carbon::parse($heartbeat);
                $diffMinutes = $lastRun->diffInMinutes(Carbon::now());

                if ($diffMinutes <= 5) {
                    return [
                        'status' => 'healthy',
                        'message' => "Scheduler heartbeat active (last run {$diffMinutes}m ago).",
                        'details' => [
                            'last_run' => $heartbeat,
                            'latency_minutes' => $diffMinutes,
                        ],
                    ];
                }

                return [
                    'status' => 'warning',
                    'message' => "Scheduler heartbeat is stale ({$diffMinutes}m old). Verify system crontab.",
                    'details' => [
                        'last_run' => $heartbeat,
                        'latency_minutes' => $diffMinutes,
                    ],
                ];
            } catch (Throwable) {
                // If timestamp parsing fails, fall through
            }
        }

        $isTesting = app()->environment('testing');

        return [
            'status' => $isTesting ? 'healthy' : 'warning',
            'message' => $isTesting
                ? 'Scheduler heartbeat not recorded in testing environment (expected).'
                : 'Scheduler heartbeat not detected. Verify system crontab (* * * * * php artisan schedule:run).',
            'details' => [
                'last_run' => null,
                'environment' => app()->environment(),
            ],
        ];
    }

    /**
     * Verify APP_KEY is configured and represents a valid 32-byte key.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkAppKey(): array
    {
        $key = (string) config('app.key');

        if ($key === '') {
            return [
                'status' => 'critical',
                'message' => 'APP_KEY is missing from environment.',
                'details' => [
                    'configured' => false,
                ],
            ];
        }

        $rawKey = $key;
        if (str_starts_with($key, 'base64:')) {
            $rawKey = base64_decode(substr($key, 7), true);
        }

        if ($rawKey === false || strlen($rawKey) !== 32) {
            return [
                'status' => 'critical',
                'message' => 'APP_KEY is invalid (expected 32-byte key for AES-256-CBC).',
                'details' => [
                    'configured' => true,
                    'valid' => false,
                    'byte_length' => is_string($rawKey) ? strlen($rawKey) : 0,
                ],
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'APP_KEY is configured and valid (32-byte AES-256-CBC key).',
            'details' => [
                'configured' => true,
                'valid' => true,
                'cipher' => (string) config('app.cipher', 'AES-256-CBC'),
            ],
        ];
    }

    /**
     * Verify SMTP mail gateway relays and broken connection status.
     *
     * @return array{status: string, message: string, details: array<string, mixed>}
     */
    public function checkSmtpGateway(): array
    {
        $brokenRelay = \App\Domain\Mail\Models\SmtpRelay::query()
            ->active()
            ->where('status', 'error')
            ->first();

        if ($brokenRelay !== null) {
            return [
                'status' => 'critical',
                'message' => "SMTP relay [{$brokenRelay->name}] connection is broken: {$brokenRelay->last_error_message}",
                'details' => [
                    'relay_id' => $brokenRelay->id,
                    'name' => $brokenRelay->name,
                    'host' => $brokenRelay->host,
                    'port' => $brokenRelay->port,
                    'error' => $brokenRelay->last_error_message,
                ],
            ];
        }

        $activeRelays = \App\Domain\Mail\Models\SmtpRelay::query()->active()->count();

        return [
            'status' => 'healthy',
            'message' => $activeRelays > 0
                ? "SMTP mail gateways operational ({$activeRelays} active relay(s) configured)."
                : 'Default system mailer active; no custom SMTP relays configured.',
            'details' => [
                'active_relays' => $activeRelays,
            ],
        ];
    }

    /**
     * Aggregate all subsystem checks and evaluate overall environment readiness.
     *
     * @return array{status: string, timestamp: string, environment: string, checks: array<string, array{status: string, message: string, details: array<string, mixed>}>, database: array, redis: array, queues: array, storage: array, scheduler: array, app_key: array}
     */
    public function runAllChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queues' => $this->checkQueues(),
            'storage' => $this->checkStorage(),
            'scheduler' => $this->checkScheduler(),
            'app_key' => $this->checkAppKey(),
            'smtp_gateway' => $this->checkSmtpGateway(),
        ];

        $statuses = array_map(fn ($c) => $c['status'], $checks);

        if (in_array('critical', $statuses, true)) {
            $overall = 'critical';
        } elseif (in_array('warning', $statuses, true)) {
            $overall = 'warning';
        } else {
            $overall = 'healthy';
        }

        return [
            'status' => $overall,
            'timestamp' => Carbon::now()->toIso8601String(),
            'environment' => app()->environment(),
            'checks' => $checks,
            'database' => $checks['database'],
            'redis' => $checks['redis'],
            'queues' => $checks['queues'],
            'storage' => $checks['storage'],
            'scheduler' => $checks['scheduler'],
            'app_key' => $checks['app_key'],
        ];
    }

    /**
     * Sanitize error messages to avoid leaking credentials or connection strings per T-12-17.
     */
    protected function sanitizeErrorMessage(string $message): string
    {
        // Redact passwords and credentials matching patterns like password=..., auth=...
        $sanitized = preg_replace('/(password|pwd|secret|key|token)=([^;&\s]+)/i', '$1=[REDACTED]', $message);

        // Redact URLs containing credentials like redis://user:password@host or redis://:password@host
        $sanitized = preg_replace('/\/\/([^:]*:)?([^@]+)@/', '//[REDACTED_AUTH]@', (string) $sanitized);

        return (string) $sanitized;
    }
}
