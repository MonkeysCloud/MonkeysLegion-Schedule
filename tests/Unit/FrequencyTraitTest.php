<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Monkeyslegion\Schedule\Task;

class FrequencyTraitTest extends TestCase
{
    public function testEveryMinuteFluentMapping(): void
    {
        $task = new Task('foo', '* * * * *');
        $task->everyMinute();
        
        $this->assertEquals('* * * * *', $task->expression);
    }

    public function testDailyFluentMappingAtMidnight(): void
    {
        $task = new Task('foo', '* * * * *');
        $task->daily();
        
        $this->assertEquals('0 0 * * *', $task->expression);
    }

    public function testHourlyFluentMappingAtMinuteZero(): void
    {
        $task = new Task('foo', '* * * * *');
        $task->hourly();
        
        $this->assertEquals('0 * * * *', $task->expression);
    }

    public function testAtTimeFluentMappingForDailyTasks(): void
    {
        $task = new Task('foo', '* * * * *');
        $task->at('14:30');
        
        $this->assertEquals('30 14 * * *', $task->expression);
    }

    public function testWeekdaysFluentMapping(): void
    {
        $task = new Task('foo', '* * * * *');
        $task->weekdays();
        
        $this->assertEquals('* * * * 1-5', $task->expression);
    }

    public function testChainingFluentFrequencies(): void
    {
        $task = new Task('foo', '* * * * *');
        $task->weekdays()->at('10:00');
        
        $this->assertEquals('0 10 * * 1-5', $task->expression);
    }
}
