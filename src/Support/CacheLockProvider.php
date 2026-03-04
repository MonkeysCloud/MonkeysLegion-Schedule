<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Support;

use MonkeysLegion\Database\Cache\Contracts\CacheInterface;
use MonkeysLegion\Schedule\Contracts\LockProvider;
use MonkeysLegion\Schedule\Task;

class CacheLockProvider implements LockProvider
{
    private string $prefix = 'schedule:lock:';

    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    /**
     * @inheritDoc
     */
    public function lock(Task $task): bool
    {
        // We use the 'add' method which only sets the value if the key doesn't exist.
        // This provides an atomic lock mechanism.
        return $this->cache->add(
            $this->getLockKey($task),
            time(),
            $task->ttl
        );
    }

    /**
     * @inheritDoc
     */
    public function unlock(Task $task): void
    {
        $this->cache->delete($this->getLockKey($task));
    }

    /**
     * @inheritDoc
     */
    public function isLocked(Task $task): bool
    {
        return $this->cache->has($this->getLockKey($task));
    }

    private function getLockKey(Task $task): string
    {
        return $this->prefix . $task->id;
    }
}
