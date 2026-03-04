<?php

namespace MonkeysLegion\Schedule\Tests\Fixtures\Scanner;

use MonkeysLegion\Schedule\Attributes\Scheduled;

class MethodTask
{
    #[Scheduled(expression: '*/5 * * * *', tags: ['periodic'])]
    public function fire() {}
}
