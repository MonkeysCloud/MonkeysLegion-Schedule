<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use Monkeyslegion\Schedule\Schedule;
use Monkeyslegion\Schedule\Support\CronParser;
use DateTimeImmutable;
use Monkeyslegion\Schedule\Events\TaskStarting;
use Monkeyslegion\Schedule\Events\TaskFinished;
use Monkeyslegion\Schedule\Events\TaskFailed;
use Throwable;

#[CommandAttr(
    'schedule:work',
    'Schedule worker daemon.'
)]
final class WorkCommand extends Command
{
    private bool $shouldQuit = false;
    private int $tickInterval = 500_000; // 0.5s

    public function __construct(
        private readonly Schedule $schedule,
        private readonly CronParser $parser
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->workerLine('WORKER STARTED', 'green');

        $this->registerSignalHandlers();

        while (! $this->shouldQuit) {
            $cycleStart = microtime(true);

            try {
                $this->runDueTasks();
                $this->runPushedTasks();
            } catch (Throwable $e) {
                $this->workerLine('FATAL ' . $e->getMessage(), 'red');

                if ($this->isVerbose()) {
                    $this->cliLine()
                        ->add($e->getTraceAsString(), 'gray')
                        ->print();
                }
            }

            $this->sleepWithDriftCorrection($cycleStart);
        }

        $this->workerLine('WORKER STOPPED', 'yellow');

        return self::SUCCESS;
    }

    private function runDueTasks(): void
    {
        $tasks = $this->schedule->getTasks();

        if (empty($tasks)) {
            return;
        }

        $now = new DateTimeImmutable();

        foreach ($tasks as $task) {
            if (! $task->isDue($this->parser, $now)) {
                continue;
            }

            $this->taskLine($task->id, 'RUN', 'cyan');
            $task->dispatchStarting($this->schedule);

            try {
                $result = $task->execute();
                $task->markAsRun($now);

                $exit = is_array($result) ? ($result['exit_code'] ?? 0) : 0;

                if ($exit === 0) {
                    $this->taskLine($task->id, 'DONE', 'green');
                    $task->dispatchFinished($this->schedule, $result);
                } else {
                    $this->taskLine($task->id, 'FAILED (' . $exit . ')', 'red');
                }
            } catch (Throwable $e) {
                $this->taskLine($task->id, 'FAILED', 'red');
                $task->dispatchFailed($this->schedule, $e);

                if ($this->isVerbose()) {
                    $this->cliLine()
                        ->add('  ', 'gray')
                        ->add($e::class . ': ' . $e->getMessage(), 'gray')
                        ->print();
                }
            }
        }
    }

    private function runPushedTasks(): void
    {
        $pending = $this->schedule->getPendingTasks();

        if (empty($pending)) {
            return;
        }

        foreach ($pending as $task) {
            $this->taskLine($task->id, 'PEND', 'yellow');
            $task->dispatchStarting($this->schedule);

            try {
                $result = $task->execute();
                $this->taskLine($task->id, 'DONE', 'green');
                $task->dispatchFinished($this->schedule, $result);
            } catch (Throwable $e) {
                $this->taskLine($task->id, 'FAILED', 'red');
                $task->dispatchFailed($this->schedule, $e);
                if ($this->isVerbose()) {
                    $this->cliLine()
                        ->add('  ', 'gray')
                        ->add($e::class . ': ' . $e->getMessage(), 'gray')
                        ->print();
                }
            }
        }
    }

    private function sleepWithDriftCorrection(float $cycleStart): void
    {
        $elapsed = (microtime(true) - $cycleStart) * 1_000_000;
        $remaining = $this->tickInterval - (int) $elapsed;

        if ($remaining > 0) {
            usleep($remaining);
        }
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, fn() => $this->shouldQuit = true);
        pcntl_signal(SIGTERM, fn() => $this->shouldQuit = true);
    }

    /**
     * Generic worker line (system-level events)
     */
    private function workerLine(string $message, string $color): void
    {
        $time = date('Y-m-d H:i:s');

        $this->cliLine()
            ->add("[{$time}] ", 'gray')
            ->add($message, $color, 'bold')
            ->print();
    }

    /**
     * Task execution line
     */
    private function taskLine(string $taskId, string $state, string $color): void
    {
        $time = date('H:i:s');

        $this->cliLine()
            ->add("[{$time}] ", 'gray')
            ->add(str_pad($state, 7), $color, 'bold')
            ->space()
            ->add($taskId, 'white')
            ->print();
    }

    private function isVerbose(): bool
    {
        return $this->hasOption('verbose');
    }
}
