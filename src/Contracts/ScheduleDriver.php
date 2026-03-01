<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Contracts;

use Monkeyslegion\Schedule\Task;

interface ScheduleDriver
{
    /**
     * Store the metadata for a task after it finishes.
     * Used for the 'Last Run' column in CLI.
     */
    public function updateTaskState(string $taskId, array $metadata): void;

    /**
     * Retrieve the last recorded state of a task.
     */
    public function getTaskState(string $taskId): ?array;

    /**
     * Push a task into the "Ready to Run" buffer.
     * This allows developers to trigger a scheduled task manually via code.
     */
    public function push(Task $task): void;

    /**
     * Retrieve and "Pop" all pending ad-hoc tasks.
     * The Daemon calls this every second to see if anything was pushed.
     * * @return array<Task>
     */
    public function popPendingTasks(): array;

    /**
     * Clear all state/locks for a specific task (Emergency Brake).
     */
    public function forget(string $taskId): void;
}