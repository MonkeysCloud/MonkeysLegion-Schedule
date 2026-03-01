<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Driver;

use Monkeyslegion\Schedule\Contracts\ScheduleDriver;
use Monkeyslegion\Schedule\Task;

class NullSchedule implements ScheduleDriver
{
    public function updateTaskState(string $taskId, array $metadata): void
    {
        // No-op
    }

    public function getTaskState(string $taskId): ?array
    {
        return null; // Always return null (no state)
    }

    public function push(Task $task): void
    {
        // No-op
    }

    public function popPendingTasks(): array
    {
        return []; // Always return empty (no pending tasks)
    }

    public function forget(string $taskId): void
    {
        // No-op
    }
}
