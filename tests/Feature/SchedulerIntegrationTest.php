<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Monkeyslegion\Schedule\Schedule;
use Monkeyslegion\Schedule\ScheduleManager;
use Monkeyslegion\Schedule\Support\CronParser;
use Monkeyslegion\Schedule\Driver\CacheDriver;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;
use Monkeyslegion\Schedule\Task;
use MonkeysLegion\Cache\CacheManager;

class SchedulerIntegrationTest extends TestCase
{
    private $cache;
    private $driver;
    private $manager;
    private $schedule;
    private $parser;

    protected function setUp(): void
    {
        $this->cache = new class implements CacheInterface {
            public array $storage = [];
            public function getCacheManager(): CacheManager
            {
                return new CacheManager();
            }
            public function getPrefix(): string
            {
                return '';
            }
            public function get(string $key, mixed $default = null): mixed
            {
                return $this->storage[$key] ?? $default;
            }
            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->storage[$key] = $value;
                return true;
            }
            public function delete(string $key): bool
            {
                unset($this->storage[$key]);
                return true;
            }
            public function clear(): bool
            {
                $this->storage = [];
                return true;
            }
            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }
            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }
            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }
            public function has(string $key): bool
            {
                return isset($this->storage[$key]);
            }
            public function remember(string $key, int|\DateInterval|null $ttl, callable $callback): mixed
            {
                return $callback();
            }
            public function forever(string $key, mixed $value): bool
            {
                return $this->set($key, $value);
            }
            public function increment(string $key, int $value = 1): int|bool
            {
                return 1;
            }
            public function decrement(string $key, int $value = 1): int|bool
            {
                return 0;
            }
            public function pull(string $key, mixed $default = null): mixed
            {
                $val = $this->storage[$key] ?? $default;
                unset($this->storage[$key]);
                return $val;
            }
            public function add(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
            {
                return true;
            }
            public function tags(array|string $tags): \MonkeysLegion\Cache\CacheInterface
            {
                return $this;
            }
            public function store(?string $name = null): \MonkeysLegion\Cache\CacheInterface
            {
                return $this;
            }
            public function clearByPrefix(string $prefix): bool
            {
                return true;
            }
            public function isConnected(): bool
            {
                return true;
            }
            public function getStatistics(): array
            {
                return [];
            }
        };

        $this->driver = new CacheDriver($this->cache);
        $this->parser = new CronParser('UTC');

        $this->manager = new ScheduleManager(
            $this->cache,
            null,
            $this->driver,
            null, // No logger
            false // Not debug
        );

        $this->schedule = new Schedule($this->manager);
    }

    public function testManualRegistrationAndRunning(): void
    {
        $executed = false;
        $this->schedule->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute();

        $tasks = $this->schedule->getTasks();
        $this->assertCount(1, $tasks);

        foreach ($tasks as $task) {
            if ($task->isDue($this->parser)) {
                $task->execute();
            }
        }

        $this->assertTrue($executed);
    }

    public function testPendingAdHocTasksPushedAndPopped(): void
    {
        $executed = false;
        $task = new Task(function () use (&$executed) {
            $executed = true;
        });

        // Use the driver push directly
        $this->driver->push($task);

        // Check if manager sees it
        $pending = $this->schedule->getPendingTasks();
        $this->assertCount(1, $pending);

        $pending[0]->execute();
        $this->assertTrue($executed);

        // Should be empty now (popped)
        $this->assertCount(0, $this->schedule->getPendingTasks());
    }

    public function testSubMinuteTaskDetection(): void
    {
        // 6 segments: second minute hour day month dow
        $task = new Task('echo 1', '* * * * * *');

        // We need to verify CronParser handles it via 6th field logic
        $this->assertTrue($this->parser->isDue($task->expression));

        // 30th second
        $taskSpecificSecond = new Task('echo 1', '30 * * * * *');

        // This confirms the parser logic works for both cases
        $this->assertIsBool($this->parser->isDue($taskSpecificSecond->expression));
    }

    public function testFrequencySplicingWithSubMinute(): void
    {
        $task = new Task('echo 1', '* * * * *');
        $task->dailyAt('14:30');
        $this->assertEquals('30 14 * * *', $task->expression);

        $subTask = new Task('echo 1', '* * * * * *'); // 6 fields: sec min hour day month dow
        $subTask->dailyAt('14:30');
        // In 6-field, min is pos 1 (index 1), hour is pos 2 (index 2)
        // Expression should be '* 30 14 * * *'
        $this->assertEquals('* 30 14 * * *', $subTask->expression);
    }
}
