<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Driver;

use Monkeyslegion\Schedule\Contracts\ScheduleDriver;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;

class DriverFactory
{
    public function __construct(
        private readonly ?CacheInterface $cache = null,
        private readonly ?\Redis $redis = null
    ) {}

    /**
     * Create a driver instance based on the given name.
     */
    public function make(string $name): ScheduleDriver
    {
        return match ($name) {
            'cache' => $this->createCacheDriver(),
            'redis' => $this->createRedisDriver(),
            'null' => new NullSchedule(),
            default => throw new \InvalidArgumentException("Unsupported schedule driver: {$name}"),
        };
    }

    /**
     * Create a RedisSchedule driver instance.
     */
    private function createRedisDriver(): RedisSchedule
    {
        if ($this->redis === null) {
            throw new \RuntimeException('Redis instance is required to create a RedisSchedule driver.');
        }

        return new RedisSchedule($this->redis);
    }

    /**
     * Create a CacheDriver instance.
     */
    private function createCacheDriver(): CacheDriver
    {
        if ($this->cache === null) {
            throw new \RuntimeException('CacheInterface is required to create a CacheDriver.');
        }

        return new CacheDriver($this->cache);
    }
}
