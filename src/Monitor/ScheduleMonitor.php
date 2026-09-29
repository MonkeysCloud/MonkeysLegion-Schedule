<?php
declare(strict_types=1);

namespace MonkeysLegion\Schedule\Monitor;

use MonkeysLegion\Schedule\Task;

/**
 * MonKeysLegion Framework — Schedule Package
 *
 * Monitors scheduled task health — detects overdue tasks, failure streaks,
 * and degraded performance.
 *
 * @copyright 2026 MonKeysCloud Team
 * @license   MIT
 */
final class ScheduleMonitor
{
    /** @var array<string, array{runs: int, failures: int, last_run: ?\DateTimeImmutable, last_duration: ?float, last_error: ?string}> */
    private array $stats = [];

    /**
     * Record a task execution result.
     */
    public function recordRun(string $taskName, bool $success, float $duration, ?string $error = null): void
    {
        if (!isset($this->stats[$taskName])) {
            $this->stats[$taskName] = [
                'runs'          => 0,
                'failures'      => 0,
                'last_run'      => null,
                'last_duration' => null,
                'last_error'    => null,
            ];
        }

        $this->stats[$taskName]['runs']++;
        $this->stats[$taskName]['last_run'] = new \DateTimeImmutable();
        $this->stats[$taskName]['last_duration'] = $duration;

        if ($success) {
            $this->stats[$taskName]['failures'] = 0;
            $this->stats[$taskName]['last_error'] = null;
        } else {
            $this->stats[$taskName]['failures']++;
            $this->stats[$taskName]['last_error'] = $error;
        }
    }

    /**
     * Check the health of a single task.
     */
    public function check(Task $task, ?\DateTimeImmutable $now = null): TaskHealth
    {
        $now = $now ?? new \DateTimeImmutable();
        $stats = $this->stats[$task->name] ?? null;

        if ($stats === null || $stats['last_run'] === null) {
            return new TaskHealth(
                name: $task->name,
                status: TaskHealth::STATUS_NEVER_RUN,
                lastRun: null,
                nextDue: null,
                overdue: false,
                failureCount: 0,
            );
        }

        $isDue = $task->isDue(new \MonkeysLegion\Schedule\CronParser(), $now);
        $lastRun = $stats['last_run'];
        $failures = $stats['failures'];
        $avgDuration = $stats['last_duration'];

        // Determine status
        $status = TaskHealth::STATUS_HEALTHY;

        if ($failures >= 3) {
            $status = TaskHealth::STATUS_UNHEALTHY;
        } elseif ($failures >= 1) {
            $status = TaskHealth::STATUS_DEGRADED;
        }

        // Check if overdue (hasn't run in 2x the expected interval)
        $overdue = false;
        $nextDue = null;
        if ($isDue && $failures === 0) {
            // Task is due but hasn't run yet — could be normal (waiting for runner)
            // Only flag as overdue if last run was more than 2 hours ago
            $hoursSinceLastRun = ($now->getTimestamp() - $lastRun->getTimestamp()) / 3600;
            if ($hoursSinceLastRun > 2) {
                $overdue = true;
                $status = TaskHealth::STATUS_DEGRADED;
            }
        }

        return new TaskHealth(
            name: $task->name,
            status: $status,
            lastRun: $lastRun,
            nextDue: $nextDue,
            overdue: $overdue,
            failureCount: $failures,
            avgDuration: $avgDuration,
            lastError: $stats['last_error'],
        );
    }

    /**
     * Check the health of all tasks.
     *
     * @param list<Task> $tasks
     * @return list<TaskHealth>
     */
    public function checkAll(array $tasks, ?\DateTimeImmutable $now = null): array
    {
        return array_map(fn(Task $t) => $this->check($t, $now), $tasks);
    }

    /**
     * Get all tasks that need attention.
     *
     * @param list<Task> $tasks
     * @return list<TaskHealth>
     */
    public function unhealthy(array $tasks, ?\DateTimeImmutable $now = null): array
    {
        return array_filter(
            $this->checkAll($tasks, $now),
            fn(TaskHealth $h) => $h->needsAttention,
        );
    }

    /**
     * Get raw stats for a task.
     *
     * @return array<string, mixed>|null
     */
    public function getStats(string $taskName): ?array
    {
        return $this->stats[$taskName] ?? null;
    }
}
