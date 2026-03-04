<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Events;

use MonkeysLegion\Schedule\Task;

class TaskStarting
{
    public function __construct(
        public readonly Task $task
    ) {}
}
