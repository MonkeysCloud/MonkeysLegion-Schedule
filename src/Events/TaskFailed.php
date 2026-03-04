<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Events;

use MonkeysLegion\Schedule\Task;
use Throwable;

class TaskFailed
{
    public function __construct(
        public readonly Task $task,
        public readonly Throwable $exception
    ) {}
}
