<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Monkeyslegion\Schedule\Driver\RedisSchedule;
use Monkeyslegion\Schedule\Task;
use Redis;

class RedisScheduleTest extends TestCase
{
    private $redis;
    private RedisSchedule $driver;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->driver = new RedisSchedule($this->redis);
    }

    public function testPushSerializesAndRPushes(): void
    {
        $task = new Task('echo 1', '* * * * *');

        $this->redis->expects($this->once())
            ->method('rPush')
            ->with(Task::CACHE_KEY_PENDING, serialize($task));

        $this->driver->push($task);
    }

    public function testPopPendingTasksClearsList(): void
    {
        $task = new Task('echo 1', '* * * * *');
        $serialized = serialize($task);

        // First call returns a task string, second returns false
        $this->redis->expects($this->exactly(2))
            ->method('lPop')
            ->with(Task::CACHE_KEY_PENDING)
            ->willReturnOnConsecutiveCalls($serialized, false);

        $result = $this->driver->popPendingTasks();

        $this->assertCount(1, $result);
        $this->assertEquals($task->id, $result[0]->id);
    }

    public function testUpdateTaskStateUsesSerialize(): void
    {
        $taskId = 'test_id';
        $metadata = ['last_run' => 'now'];

        $this->redis->expects($this->once())
            ->method('set')
            ->with(Task::CACHE_KEY_STATE . $taskId, serialize($metadata));

        $this->driver->updateTaskState($taskId, $metadata);
    }

    public function testForgetDeletesKey(): void
    {
        $taskId = 'test_id';

        $this->redis->expects($this->once())
            ->method('del')
            ->with(Task::CACHE_KEY_STATE . $taskId);

        $this->driver->forget($taskId);
    }
}
