<?php

namespace Monkeyslegion\Schedule\Tests\Fixtures\Scanner;

use Monkeyslegion\Schedule\Attributes\Scheduled;

class MethodTask
{
    #[Scheduled(expression: '*/5 * * * *', tags: ['periodic'])]
    public function fire() {}
}
