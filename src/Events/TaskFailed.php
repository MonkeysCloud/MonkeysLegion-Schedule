<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Events;

use Monkeyslegion\Schedule\Task;
use Throwable;

class TaskFailed
{
    public function __construct(
        public readonly Task $task,
        public readonly Throwable $exception
    ) {}
}
