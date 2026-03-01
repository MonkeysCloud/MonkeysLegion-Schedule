<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule;

use Closure;
use DateTimeImmutable;
use Monkeyslegion\Schedule\Support\CronParser;
use Monkeyslegion\Schedule\Traits\ManagesFrequencies;
use RuntimeException;

class Task
{
    use ManagesFrequencies;

    public const DEFAULT_TTL = 3600;
    public const CACHE_PREFIX = 'ml_schedule:';
    public const CACHE_KEY_TASKS = 'ml_schedule:tasks';
    public const CACHE_KEY_PENDING = 'ml_schedule:pending';
    public const CACHE_KEY_STATE = 'ml_schedule:state:';

    public string $id;

    /**
     * Tracks last execution to prevent duplicate runs.
     */
    private ?DateTimeImmutable $lastRun = null;

    /** @var array<Closure> */
    private array $beforeCallbacks = [];

    /** @var array<Closure> */
    private array $afterCallbacks = [];

    /** @var array<Closure> */
    private array $successCallbacks = [];

    /** @var array<Closure> */
    private array $failureCallbacks = [];

    /** @var array<string, mixed> */
    public array $metadata = [];

    public function __construct(
        public string|Closure|array $action,
        public string $expression = '* * * * *',
        public array $tags = [],
        public bool $withoutOverlapping = true,
        public int $ttl = self::DEFAULT_TTL,
        public bool $onOneServer = false,
        ?string $name = null,
        public ?\MonkeysLegion\Logger\Contracts\MonkeysLoggerInterface $logger = null,
        public ?string $startingEvent = null,
        public ?string $finishedEvent = null,
        public ?string $failedEvent = null
    ) {
        $this->id = $name ?? $this->generateId($action);
    }

    /**
     * Determine if task is due.
     */
    public function isDue(CronParser $parser, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        if (! $parser->isDue($this->expression, $now)) {
            return false;
        }

        // Detect precision based on segments
        $expression = trim($this->expression);
        $partsCount = count(explode(' ', $expression));
        $format = ($partsCount === 6) ? 'Y-m-d H:i:s' : 'Y-m-d H:i';

        // Prevent duplicate execution in the same precision window
        if (
            $this->lastRun &&
            $this->lastRun->format($format) === $now->format($format)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Mark execution timestamp.
     */
    public function markAsRun(?DateTimeImmutable $time = null): void
    {
        $this->lastRun = $time ?? new DateTimeImmutable();
    }

    /**
     * Execute the task action.
     */
    public function execute(): mixed
    {
        $this->logger?->info("Task execution started: {$this->id}");
        $this->triggerBeforeCallbacks();

        try {
            if (is_string($this->action)) {
                $result = $this->executeCommandString($this->action);
                $this->logCommandResult($result);
                $this->handleExecutionResult($result);
                return $result;
            }

            if ($this->action instanceof Closure) {
                $result = ($this->action)();
                $this->logger?->info("Task finished (Closure): {$this->id}");
                $this->handleExecutionResult($result);
                return $result;
            }

            if (is_array($this->action)) {
                $result = $this->executeCallableArray($this->action);
                $this->handleExecutionResult($result);
                return $result;
            }
        } catch (\Throwable $e) {
            $this->triggerFailureCallbacks($e);
            $this->triggerAfterCallbacks();
            $this->logger?->error("Task failed: {$this->id}", ['error' => $e->getMessage()]);
            throw $e;
        }

        return null;
    }

    /**
     * Register a callback to run before task starts.
     */
    public function onStart(callable $callback): self
    {
        $this->beforeCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback to run after task finishes successfully.
     */
    public function onSuccess(callable $callback): self
    {
        $this->successCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback to run if task fails.
     */
    public function onFailure(callable $callback): self
    {
        $this->failureCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register a callback to run after task finishes (success or failure).
     */
    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * Attach custom metadata to the task.
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function dispatchStarting(Schedule $schedule): void
    {
        $class = $this->startingEvent ?? \Monkeyslegion\Schedule\Events\TaskStarting::class;
        $schedule->dispatch(new $class($this));
    }

    public function dispatchFinished(Schedule $schedule, mixed $result): void
    {
        $class = $this->finishedEvent ?? \Monkeyslegion\Schedule\Events\TaskFinished::class;
        $schedule->dispatch(new $class($this, $result));
    }

    public function dispatchFailed(Schedule $schedule, \Throwable $exception): void
    {
        $class = $this->failedEvent ?? \Monkeyslegion\Schedule\Events\TaskFailed::class;
        $schedule->dispatch(new $class($this, $exception));
    }

    private function triggerBeforeCallbacks(): void
    {
        foreach ($this->beforeCallbacks as $callback) {
            $callback($this);
        }
    }

    private function triggerSuccessCallbacks(mixed $result): void
    {
        foreach ($this->successCallbacks as $callback) {
            $callback($this, $result);
        }
    }

    private function triggerFailureCallbacks(\Throwable $exception): void
    {
        foreach ($this->failureCallbacks as $callback) {
            $callback($this, $exception);
        }
    }

    private function triggerAfterCallbacks(): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $callback($this);
        }
    }

    private function handleExecutionResult(mixed $result): void
    {
        // For command strings, success is exit 0.
        // For closures/callables, success is not throwing.
        if (is_array($result) && isset($result['exit_code'])) {
            if ($result['exit_code'] !== 0) {
                $this->triggerFailureCallbacks(new RuntimeException("Command failed with exit code {$result['exit_code']}"));
                return;
            }
        }

        $this->triggerSuccessCallbacks($result);
        $this->triggerAfterCallbacks();
    }

    /**
     * Execute: php ml <command> [args...]
     */
    private function executeCommandString(string $commandString): array|null
    {
        // Split command safely into arguments
        $parts = preg_split('/\s+/', trim($commandString)) ?: [];

        if (empty($parts)) {
            return null;
        }

        $command = array_merge(
            [PHP_BINARY, 'ml'],
            $parts
        );

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $output = stream_get_contents($pipes[1]);
        $error  = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'output' => trim((string) $output),
            'error' => trim((string) $error),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Execute [ClassName::class, 'method', [args]]
     */
    private function executeCallableArray(array $action): mixed
    {
        if (count($action) < 2) {
            throw new RuntimeException('Invalid callable task format.');
        }

        [$class, $method] = $action;
        $args = $action[2] ?? [];

        if (! is_string($class) || ! is_string($method)) {
            throw new RuntimeException('Class and method must be strings.');
        }

        if (! class_exists($class)) {
            throw new RuntimeException("Class {$class} not found.");
        }

        $instance = new $class();

        if (! method_exists($instance, $method)) {
            throw new RuntimeException("Method {$method} not found on {$class}.");
        }

        if (! is_array($args)) {
            throw new RuntimeException('Arguments must be an array.');
        }

        try {
            $result = $instance->$method(...$args);
            $this->logger?->info("Task finished: {$this->id}", ['result' => $result]);
            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error("Task failed: {$this->id}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function logCommandResult(mixed $result): void
    {
        if (!$this->logger || !is_array($result)) {
            return;
        }

        if ($result['exit_code'] === 0) {
            $this->logger->info("Task finished: {$this->id}");
        } else {
            $this->logger->error("Task failed: {$this->id} (exit {$result['exit_code']})", [
                'error' => $result['error']
            ]);
        }

        if ($result['output']) {
            $this->logger->debug("Task output: {$this->id}", ['output' => $result['output']]);
        }
    }

    private function generateId(mixed $action): string
    {
        if (is_string($action)) {
            return md5($action);
        }

        if ($action instanceof Closure) {
            return 'closure_' . spl_object_hash($action);
        }

        if (is_array($action)) {
            return md5(serialize($action));
        }

        return uniqid('task_', true);
    }
}
