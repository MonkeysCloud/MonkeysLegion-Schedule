<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Monkeyslegion\Schedule\Discovery\AttributeScanner;
use Monkeyslegion\Schedule\Task;

class AttributeScannerTest extends TestCase
{
    public function testScansAndDiscoversAttributedClasses(): void
    {
        // Use the explicit fixtures directory for testing
        $fixturesDir = __DIR__ . '/../Fixtures/Scanner';
        $scanner = new AttributeScanner([''], $fixturesDir);
        $tasks = $scanner->scan();

        $this->assertCount(2, $tasks);

        // Find DailyTask
        $dailyTasks = array_values(array_filter($tasks, fn($t) => str_contains(serialize($t->action), 'DailyTask')));
        $this->assertCount(1, $dailyTasks);
        $daily = $dailyTasks[0];
        $this->assertEquals('0 0 * * *', $daily->expression);
        $this->assertContains('daily', $daily->tags);

        // Find MethodTask
        $methodTasks = array_values(array_filter($tasks, fn($t) => str_contains(serialize($t->action), 'MethodTask')));
        $this->assertCount(1, $methodTasks);
        $method = $methodTasks[0];
        $this->assertEquals('*/5 * * * *', $method->expression);
        $this->assertContains('periodic', $method->tags);
    }
}
