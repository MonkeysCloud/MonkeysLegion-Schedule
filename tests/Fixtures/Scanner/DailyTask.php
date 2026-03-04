<?php

namespace MonkeysLegion\Schedule\Tests\Fixtures\Scanner;

use MonkeysLegion\Schedule\Attributes\Scheduled;

#[Scheduled(expression: '0 0 * * *', tags: ['daily'])]
class DailyTask
{
    public function __invoke() {}
}
