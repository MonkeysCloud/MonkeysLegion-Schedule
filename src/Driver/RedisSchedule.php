<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Driver;

use Monkeyslegion\Schedule\Contracts\ScheduleDriver;
use Monkeyslegion\Schedule\Task;
use Redis;

class RedisSchedule implements ScheduleDriver
{
    public function __construct(
        private readonly Redis $redis
    ) {}

    /**
     * Store the metadata for a task after it finishes.
     */
    public function updateTaskState(string $taskId, array $metadata): void
    {
        $this->redis->set(
            $this->taskStateKey($taskId),
            serialize($metadata)
        );
    }

    /**
     * Retrieve the last recorded state of a task.
     */
    public function getTaskState(string $taskId): ?array
    {
        $state = $this->redis->get($this->taskStateKey($taskId));

        if ($state === false) {
            return null;
        }

        return unserialize($state);
    }

    /**
     * Push a task into the "Ready to Run" list.
     */
    public function push(Task $task): void
    {
        $this->redis->rPush(Task::CACHE_KEY_PENDING, serialize($task));
    }

    /**
     * Retrieve and "Pop" all pending ad-hoc tasks.
     * Uses atomic LPOP in a loop.
     */
    public function popPendingTasks(): array
    {
        $tasks = [];

        // Pull everything from the list atomically
        while ($data = $this->redis->lPop(Task::CACHE_KEY_PENDING)) {
            $task = unserialize($data);
            if ($task instanceof Task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    /**
     * Clear all state/locks for a specific task.
     */
    public function forget(string $taskId): void
    {
        $this->redis->del($this->taskStateKey($taskId));
    }

    /**
     * Generate the state key for Redis.
     */
    private function taskStateKey(string $taskId): string
    {
        return Task::CACHE_KEY_STATE . $taskId;
    }
}
