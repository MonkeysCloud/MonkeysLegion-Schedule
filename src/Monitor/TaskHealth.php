<?php
declare(strict_types=1);

namespace MonkeysLegion\Schedule\Monitor;

/**
 * MonKeysLegion Framework — Schedule Package
 *
 * DTO representing the health status of a scheduled task.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class TaskHealth
{
    public const STATUS_HEALTHY   = 'healthy';
    public const STATUS_DEGRADED  = 'degraded';
    public const STATUS_UNHEALTHY = 'unhealthy';
    public const STATUS_NEVER_RUN = 'never_run';

    /**
     * @param string                    $name          Task name.
     * @param string                    $status        Health status.
     * @param \DateTimeImmutable|null   $lastRun       Last execution time.
     * @param \DateTimeImmutable|null   $nextDue       Next scheduled run.
     * @param bool                      $overdue       Whether the task is overdue.
     * @param int                       $failureCount  Consecutive failures.
     * @param float|null                $avgDuration   Average duration in seconds.
     * @param string|null               $lastError     Last error message.
     * @param array<string, mixed>      $metadata      Extra info.
     */
    public function __construct(
        public string $name,
        public string $status,
        public ?\DateTimeImmutable $lastRun = null,
        public ?\DateTimeImmutable $nextDue = null,
        public bool $overdue = false,
        public int $failureCount = 0,
        public ?float $avgDuration = null,
        public ?string $lastError = null,
        public array $metadata = [],
    ) {}

    /**
     * Whether the task needs attention.
     */
    public bool $needsAttention {
        get => in_array($this->status, [self::STATUS_UNHEALTHY, self::STATUS_DEGRADED, self::STATUS_NEVER_RUN], true);
    }
}
