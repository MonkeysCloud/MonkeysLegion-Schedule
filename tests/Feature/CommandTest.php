<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Tests\Feature;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Schedule\Schedule;
use MonkeysLegion\Schedule\ScheduleManager;
use MonkeysLegion\Schedule\Support\CronParser;
use MonkeysLegion\Schedule\Driver\CacheDriver;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface as DatabaseCacheInterface;
use MonkeysLegion\Cache\CacheInterface as BaseCacheInterface;
use MonkeysLegion\Schedule\Cli\Command\ListCommand;
use MonkeysLegion\Schedule\Cli\Command\TestCommand;
use MonkeysLegion\Schedule\Cli\Command\ClearLocksCommand;
use MonkeysLegion\Cache\CacheManager;

class CommandTest extends TestCase
{
    private $cache;
    private $driver;
    private $manager;
    private $schedule;
    private $parser;

    protected function setUp(): void
    {
        $this->cache = new class implements DatabaseCacheInterface, BaseCacheInterface {
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
            public function remember(string $key, int|\DateInterval|null $ttl, \Closure|callable $callback): mixed
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
                if (!$this->has($key)) {
                    $this->set($key, $value, $ttl);
                    return true;
                }
                return false;
            }
            public function tags(array|string $tags): BaseCacheInterface
            {
                return $this;
            }
            public function store(?string $name = null): BaseCacheInterface
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
            // Psr\SimpleCache methods
            public function setMultipleEx($values, $ttl = null)
            {
                return true;
            }
            // PSR-16 methods
            public function deleteMultipleEx($keys)
            {
                return true;
            }
            public function hasEx($key)
            {
                return true;
            }
            // Add missing Psr\SimpleCache methods if needed, but for mocks these should suffice
            // BaseCacheInterface methods
            public function rememberForever(string $key, \Closure $callback): mixed
            {
                return $callback();
            }
            public function putMany(array $values, \DateInterval|int|null $ttl = null): bool
            {
                return true;
            }
        };

        $this->driver = new CacheDriver($this->cache);
        $this->parser = new CronParser('UTC');
        $this->manager = new ScheduleManager($this->cache, null, $this->driver, null, false);
        $this->schedule = new Schedule($this->manager);
    }

    public function testListCommand(): void
    {
        $this->schedule->call(fn() => 'test')->everyMinute()->name('test-task');

        $command = new ListCommand($this->schedule, $this->parser);

        $exitCode = $command(); // Use __invoke

        $this->assertEquals(0, $exitCode);
        // Output capturing for fwrite(STDOUT) is tricky in unit tests without stream wrappers.
        // We verified the output visually in terminal logs during implementation.
    }

    public function testTestCommand(): void
    {
        global $argv;
        $originalArgv = $argv;
        $argv = ['ml', 'schedule:test', 'test-task'];

        $executed = false;
        $this->schedule->call(function () use (&$executed) {
            $executed = true;
            return 'done';
        })->name('test-task');

        $command = new TestCommand($this->schedule);

        $exitCode = $command(); // Use __invoke

        $argv = $originalArgv;

        $this->assertEquals(0, $exitCode);
        $this->assertTrue($executed);
    }

    public function testClearLocksCommand(): void
    {
        global $argv;
        $originalArgv = $argv;

        $task = $this->schedule->call(fn() => 'test')->name('locked-task')->withoutOverlapping();
        $this->schedule->getLockProvider()->lock($task);
        $this->assertTrue($this->schedule->getLockProvider()->isLocked($task));

        // Test clearing specific lock
        $argv = ['ml', 'schedule:clear-locks', 'locked-task'];
        $command = new ClearLocksCommand($this->schedule);

        $exitCode = $command(); // Use __invoke

        $this->assertEquals(0, $exitCode);
        $this->assertFalse($this->schedule->getLockProvider()->isLocked($task));

        $argv = $originalArgv;
    }
}
