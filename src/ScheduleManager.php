<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule;

use Closure;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;
use Monkeyslegion\Schedule\Contracts\ScheduleDriver;
use Monkeyslegion\Schedule\Discovery\AttributeScanner;

class ScheduleManager
{
    /** @var array<Task> */
    private array $tasks = [];

    /** @var array<string, array<Closure>> */
    private array $listeners = [];

    public function __construct(
        private readonly ?CacheInterface $cache,
        private readonly ?AttributeScanner $scanner,
        private readonly ScheduleDriver $driver,
        private ?\MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface $logger = null,
        private readonly bool $debugMode = false
    ) {
        if ($this->debugMode && !$this->scanner) {
            throw new \InvalidArgumentException('AttributeScanner is required in debug mode for dynamic discovery.');
        } else if (!$this->debugMode && !$this->cache) {
            throw new \InvalidArgumentException('CacheInterface is required in production mode for caching tasks.');
        }
        if (!$this->debugMode) {
            $this->logger = null; // Disable dev logging in production mode
        }
        $this->boot();
    }

    private function boot(): void
    {
        $this->tasks = match ($this->debugMode) {
            true => $this->scanner?->scan() ?? [],
            false => $this->cache?->get(Task::CACHE_KEY_TASKS) ?? [],
            default => []
        };

        if ($this->logger) {
            foreach ($this->tasks as $task) {
                $task->logger ??= $this->logger;
            }
        }
    }

    /**
     * Manual Registration: Add a Closure task.
     */
    public function call(Closure $action, ?string $name = null): Task
    {
        $task = new Task(
            action: $action,
            expression: '* * * * *', // Default to every minute
            name: $name,
            logger: $this->logger
        );

        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Manual Registration: Add a Shell/CLI command.
     */
    public function command(string $command, ?string $name = null): Task
    {
        $task = new Task(
            action: $command,
            expression: '* * * * *',
            name: $name,
            logger: $this->logger
        );

        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Manual Registration: Add an invokable class task.
     */
    public function job(string $job, string $method = '__invoke'): Task
    {
        return $this->call(function () use ($job, $method) {
            return (new $job())->$method();
        }, $job);
    }

    /**
     * The Reactivity Point: 
     * Get all tasks + any ad-hoc tasks pushed to the driver.
     * * @return array<Task>
     */
    public function getDueTasks(): array
    {
        // 1. Get the static scheduled tasks
        $allTasks = $this->tasks;

        // 2. Merge with dynamic "Pushed" tasks from Redis/DB via the Driver
        $pending = $this->driver->popPendingTasks();

        return array_merge($allTasks, $pending);
    }

    /**
     * Access the driver for state updates or lock clearing.
     */
    public function driver(): ScheduleDriver
    {
        return $this->driver;
    }

    /**
     * Push a task for immediate (ad-hoc) execution via the driver.
     */
    public function push(Task $task): void
    {
        $this->driver->push($task);
    }

    /**
     * Get and clear all pending/ad-hoc tasks from the driver.
     * @return array<Task>
     */
    public function getPendingTasks(): array
    {
        return $this->driver->popPendingTasks();
    }

    /**
     * Register a listener for a system-wide event.
     */
    public function listen(string $event, Closure $callback): void
    {
        $this->listeners[$event][] = $callback;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(object $event): void
    {
        $class = get_class($event);
        foreach ($this->listeners[$class] ?? [] as $callback) {
            $callback($event);
        }
    }

    /**
     * Get all registered tasks (useful for schedule:list).
     */
    public function all(): array
    {
        return $this->tasks;
    }
}
