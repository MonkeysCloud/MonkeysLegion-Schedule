<?php

namespace Monkeyslegion\Schedule\Tests\Fixtures\Scanner;

use Monkeyslegion\Schedule\Attributes\Scheduled;

#[Scheduled(expression: '0 0 * * *', tags: ['daily'])]
class DailyTask
{
    public function __invoke() {}
}
