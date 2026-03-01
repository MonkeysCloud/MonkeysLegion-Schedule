<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule;

use Closure;

class Schedule
{
    public function __construct(
        protected ScheduleManager $manager
    ) {}

    /**
     * Register a new Closure-based task.
     */
    public function call(Closure|callable $callback, array $parameters = [], ?string $name = null): Task
    {
        // Wrap the callable to handle parameters if needed
        $action = function () use ($callback, $parameters) {
            return $callback(...$parameters);
        };

        return $this->manager->call($action, $name);
    }

    /**
     * Register a new CLI/Shell command task.
     */
    public function command(string $command, ?string $name = null): Task
    {
        return $this->manager->command($command, $name);
    }

    public function job(string $job, string $method = '__invoke'): Task
    {
        return $this->manager->call(function () use ($job, $method) {
            return (new $job())->$method();
        }, $job);
    }

    /**
     * Proxy to get all pending ad-hoc tasks.
     * @return array<Task>
     */
    public function getPendingTasks(): array
    {
        return $this->manager->getPendingTasks();
    }

    /**
     * Proxy to get all registered tasks for the Runner.
     */
    public function getTasks(): array
    {
        return $this->manager->all();
    }

    /**
     * Register a listener for a system-wide event.
     */
    public function listen(string $event, Closure $callback): void
    {
        $this->manager->listen($event, $callback);
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(object $event): void
    {
        $this->manager->dispatch($event);
    }
}
