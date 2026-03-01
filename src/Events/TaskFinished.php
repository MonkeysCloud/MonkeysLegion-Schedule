<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Events;

use Monkeyslegion\Schedule\Task;

class TaskFinished
{
    public function __construct(
        public readonly Task $task,
        public readonly mixed $result
    ) {}
}
