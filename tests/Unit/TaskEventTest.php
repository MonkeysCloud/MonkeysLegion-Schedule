<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Schedule\Task;
use MonkeysLegion\Schedule\Events\TaskStarting;
use MonkeysLegion\Schedule\Events\TaskFinished;
use MonkeysLegion\Schedule\Events\TaskFailed;
use RuntimeException;

class TaskEventTest extends TestCase
{
    public function testTaskCallbacks(): void
    {
        $started = false;
        $succeeded = false;
        $after = false;

        $task = new Task(function () {
            return 'result';
        });

        $task->onStart(function () use (&$started) {
            $started = true;
        })->onSuccess(function ($t, $res) use (&$succeeded) {
            $succeeded = ($res === 'result');
        })->after(function () use (&$after) {
            $after = true;
        })->setMetadata('foo', 'bar');

        $task->execute();

        $this->assertTrue($started);
        $this->assertTrue($succeeded);
        $this->assertTrue($after);
        $this->assertEquals('bar', $task->metadata['foo']);
    }

    public function testTaskFailureCallbacks(): void
    {
        $failed = false;
        $after = false;

        $task = new Task(function () {
            throw new RuntimeException('fail');
        });

        $task->onFailure(function ($t, $e) use (&$failed) {
            $failed = ($e->getMessage() === 'fail');
        })->after(function () use (&$after) {
            $after = true;
        });

        try {
            $task->execute();
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertTrue($failed);
        $this->assertTrue($after);
    }
}
