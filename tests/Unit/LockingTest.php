<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Schedule\Task;
use MonkeysLegion\Schedule\Support\CacheLockProvider;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;

class LockingTest extends TestCase
{
    public function testLockAcquisitionAndRelease(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $task = new Task(fn() => 'test', name: 'test-task');
        $task->withoutOverlapping(60);

        $lockProvider = new CacheLockProvider($cache);

        $cache->expects($this->once())
            ->method('add')
            ->with('schedule:lock:test-task', $this->anything(), 60)
            ->willReturn(true);

        $cache->expects($this->once())
            ->method('delete')
            ->with('schedule:lock:test-task')
            ->willReturn(true);

        $this->assertTrue($lockProvider->lock($task));
        $lockProvider->unlock($task);
    }

    public function testLockFailureIfAlreadyLocked(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $task = new Task(fn() => 'test', name: 'test-task');

        $lockProvider = new CacheLockProvider($cache);

        $cache->expects($this->once())
            ->method('add')
            ->willReturn(false);

        $this->assertFalse($lockProvider->lock($task));
    }

    public function testIsLockedChecksCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $task = new Task(fn() => 'test', name: 'test-task');

        $lockProvider = new CacheLockProvider($cache);

        $cache->expects($this->once())
            ->method('has')
            ->with('schedule:lock:test-task')
            ->willReturn(true);

        $this->assertTrue($lockProvider->isLocked($task));
    }
}
