<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Events;

use Monkeyslegion\Schedule\Task;

class TaskStarting
{
    public function __construct(
        public readonly Task $task
    ) {}
}
