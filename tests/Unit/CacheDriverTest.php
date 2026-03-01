<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Monkeyslegion\Schedule\Driver\CacheDriver;
use Monkeyslegion\Schedule\Task;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;

class CacheDriverTest extends TestCase
{
    private $cache;
    private CacheDriver $driver;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->driver = new CacheDriver($this->cache);
    }

    public function testPushAddsToPendingList(): void
    {
        $task = new Task('echo 1', '* * * * *');

        $this->cache->expects($this->once())
            ->method('get')
            ->with(Task::CACHE_KEY_PENDING, [])
            ->willReturn([]);

        $this->cache->expects($this->once())
            ->method('set')
            ->with(Task::CACHE_KEY_PENDING, [$task]);

        $this->driver->push($task);
    }

    public function testPopPendingTasksRetrievesAndClears(): void
    {
        $task = new Task('echo 1', '* * * * *');

        $this->cache->expects($this->once())
            ->method('pull')
            ->with(Task::CACHE_KEY_PENDING, [])
            ->willReturn([$task]);

        $result = $this->driver->popPendingTasks();

        $this->assertCount(1, $result);
        $this->assertSame($task, $result[0]);
    }

    public function testUpdateTaskStateUsesCorrectKey(): void
    {
        $taskId = 'test_id';
        $metadata = ['last_run' => 'now'];

        $this->cache->expects($this->once())
            ->method('set')
            ->with(Task::CACHE_KEY_STATE . $taskId, $metadata);

        $this->driver->updateTaskState($taskId, $metadata);
    }
}
