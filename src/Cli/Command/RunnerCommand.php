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
    'schedule:run',
    'The Schedule Runner. Executes due tasks based on their Cron expressions.'
)]
final class RunnerCommand extends Command
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly CronParser $parser
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->workerLine('RUNNER STARTED', 'green');

        try {
            $now = new DateTimeImmutable();

            // 1. Static Scheduled Tasks
            foreach ($this->schedule->getTasks() as $task) {
                try {
                    if (! $task->isDue($this->parser, $now)) {
                        if ($this->isVerbose()) {
                            $this->taskLine($task->id, 'SKIP', 'gray');
                        }
                        continue;
                    }

                    $this->taskLine($task->id, 'RUN', 'cyan');
                    $task->dispatchStarting($this->schedule);

                    $result = $task->execute();
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

            // 2. Pushed (Ad-hoc) Tasks
            $pending = $this->schedule->getPendingTasks();

            foreach ($pending as $task) {
                try {
                    $this->taskLine($task->id, 'PEND', 'yellow');
                    $task->dispatchStarting($this->schedule);
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

            $this->workerLine('RUNNER FINISHED', 'green');

            return self::SUCCESS;
        } catch (Throwable $e) {

            $this->workerLine('FATAL ' . $e->getMessage(), 'red');

            if ($this->isVerbose()) {
                $this->cliLine()
                    ->add($e->getTraceAsString(), 'gray')
                    ->print();
            }

            return self::FAILURE;
        }
    }

    private function workerLine(string $message, string $color): void
    {
        $time = date('Y-m-d H:i:s');

        $this->cliLine()
            ->add("[{$time}] ", 'gray')
            ->add($message, $color, 'bold')
            ->print();
    }

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
        return $this->hasOption('v') || $this->hasOption('verbose');
    }
}
