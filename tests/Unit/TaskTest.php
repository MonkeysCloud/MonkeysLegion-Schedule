<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Monkeyslegion\Schedule\Task;
use Monkeyslegion\Schedule\Support\CronParser;

class TaskTest extends TestCase
{
    public function testUniqueIDGenerationForDifferentActions(): void
    {
        $task1 = new Task('echo hello', '* * * * *');
        $task2 = new Task('echo world', '* * * * *');

        $this->assertNotEquals($task1->id, $task2->id);
    }

    public function testPersistentIDForIdenticalStringActions(): void
    {
        $task1 = new Task('echo same', '* * * * *');
        $task2 = new Task('echo same', '* * * * *');

        $this->assertEquals($task1->id, $task2->id);
    }

    public function testExecutionOfClosureAction(): void
    {
        $executed = false;
        $task = new Task(function () use (&$executed) {
            $executed = true;
            return 'done';
        }, '* * * * *');

        $result = $task->execute();

        $this->assertTrue($executed);
        $this->assertEquals('done', $result);
    }

    public function testExecutionOfIsolatedStringAction(): void
    {
        // Testing with a simple echo
        $task = new Task('echo "monkeys"', '* * * * *');
        $result = $task->execute();

        $this->assertIsArray($result);
        $this->assertEquals('monkeys', $result['output']);
        $this->assertEquals(0, $result['exit_code']);
    }

    public function testInvokableClassAction(): void
    {
        $task = new Task([TestInvokable::class, '__invoke', []], '* * * * *');
        $result = $task->execute();

        $this->assertEquals('invoked', $result);
    }

    public function testMethodWithArgumentsAction(): void
    {
        $task = new Task([TestArgs::class, 'sum', [5, 10]], '* * * * *');
        $result = $task->execute();

        $this->assertEquals(15, $result);
    }
}

/** Helper classes for testing */
class TestInvokable
{
    public function __invoke()
    {
        return 'invoked';
    }
}

class TestArgs
{
    public function sum(int $a, int $b)
    {
        return $a + $b;
    }
}
