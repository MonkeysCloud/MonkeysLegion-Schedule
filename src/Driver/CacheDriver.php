<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Driver;

use Monkeyslegion\Schedule\Contracts\ScheduleDriver;
use Monkeyslegion\Schedule\Task;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;

class CacheDriver implements ScheduleDriver
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function updateTaskState(string $taskId, array $metadata): void
    {
        $this->cache->set($this->taskStateKey($taskId), $metadata);
    }

    public function getTaskState(string $taskId): ?array
    {
        return $this->cache->get($this->taskStateKey($taskId));
    }

    public function push(Task $task): void
    {
        $tasks = $this->cache->get(Task::CACHE_KEY_PENDING, []);
        $tasks[] = $task;
        $this->cache->set(Task::CACHE_KEY_PENDING, $tasks);
    }

    public function popPendingTasks(): array
    {
        $tasks = $this->cache->pull(Task::CACHE_KEY_PENDING, []);
        return is_array($tasks) ? $tasks : [];
    }

    public function forget(string $taskId): void
    {
        $this->cache->delete($this->taskStateKey($taskId));
    }

    private function taskStateKey(string $taskId): string
    {
        return Task::CACHE_KEY_STATE . $taskId;
    }
}