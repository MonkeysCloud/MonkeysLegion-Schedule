<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Schedule\Schedule;

#[CommandAttr(
    'schedule:clear-locks',
    'Clear all task locks or a specific task lock.'
)]
final class ClearLocksCommand extends Command
{
    public function __construct(
        private readonly Schedule $schedule
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $id = $this->argument(0);
        $lockProvider = $this->schedule->getLockProvider();

        if ($lockProvider === null) {
            $this->cliLine()
                ->error('Error: ')
                ->add('No lock provider configured.')
                ->print();
            return self::FAILURE;
        }

        if ($id !== null) {
            $task = $this->schedule->getTask($id);
            if ($task === null) {
                // Task object required for lock clearing?
                // CacheLockProvider::unlock needs Task instance.
                $this->cliLine()->error("Task {$id} not found.")->print();
                return self::FAILURE;
            }

            $lockProvider->unlock($task);
            $this->cliLine()
                ->success('Lock cleared for task: ')
                ->add($id, 'yellow')
                ->print();
            return self::SUCCESS;
        }

        $overwrite = $this->ask('Are you sure you want to clear ALL task locks? (y/N)');
        if (\strtolower($overwrite) !== 'y') {
            $this->cliLine()->muted('Operation cancelled.')->print();
            return self::SUCCESS;
        }

        // Implementation of clear-all depends on the lock provider.
        // For CacheLockProvider, it knows its prefix.
        // But the contract doesn't have clearAll.
        // If it's a Redis driver/Cache driver, maybe we can clear by prefix?
        // Let's iterate tasks and clear each one.

        $tasks = $this->schedule->getTasks();
        $cleared = 0;
        foreach ($tasks as $task) {
            if ($lockProvider->isLocked($task)) {
                $lockProvider->unlock($task);
                $cleared++;
            }
        }

        $this->cliLine()
            ->success('Cleared ')
            ->add((string)$cleared, 'white', 'bold')
            ->add(' locks.', 'green')
            ->print();

        return self::SUCCESS;
    }
}
