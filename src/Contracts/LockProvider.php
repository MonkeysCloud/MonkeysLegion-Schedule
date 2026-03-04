<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Contracts;

use Monkeyslegion\Schedule\Task;

interface LockProvider
{
    /**
     * Attempt to acquire a lock for the task.
     */
    public function lock(Task $task): bool;

    /**
     * Release the lock for the task.
     */
    public function unlock(Task $task): void;

    /**
     * Check if the task is currently locked.
     */
    public function isLocked(Task $task): bool;
}
