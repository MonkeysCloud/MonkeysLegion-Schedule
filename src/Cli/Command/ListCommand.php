<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use Monkeyslegion\Schedule\Schedule;
use Monkeyslegion\Schedule\Support\CronParser;

#[CommandAttr(
    'schedule:list',
    'List all registered tasks and their next run times.'
)]
final class ListCommand extends Command
{
    public function __construct(
        private readonly Schedule $schedule,
        private readonly CronParser $parser
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $tasks = $this->schedule->getTasks();

        if (empty($tasks)) {
            $this->cliLine()->warning('No tasks found.')->print();
            return self::SUCCESS;
        }

        $this->cliLine()
            ->add('Registered Tasks (', 'gray')
            ->add((string)count($tasks), 'white', 'bold')
            ->add(')', 'gray')
            ->print();

        $this->cliLine()->space()->print();

        // Print Header
        $this->row('ID', 'EXPRESSION', 'NEXT RUN', 'STATUS');
        $this->cliLine()->add(str_repeat('-', 80), 'gray')->print();

        foreach ($tasks as $task) {
            $next = $this->parser->nextRun($task->expression);
            $lockProvider = $this->schedule->getLockProvider();
            $status = 'IDLE';

            if ($lockProvider && $lockProvider->isLocked($task)) {
                $status = 'RUNNING';
            }

            $this->row(
                $task->id,
                $task->expression,
                $next->format('Y-m-d H:i:s'),
                $status,
                $status === 'RUNNING' ? 'yellow' : 'green'
            );
        }

        $this->cliLine()->space()->print();

        return self::SUCCESS;
    }

    private function row(string $id, string $expression, string $next, string $status, string $statusColor = 'white'): void
    {
        $this->cliLine()
            ->add(str_pad($id, 30), 'white')
            ->add(str_pad($expression, 15), 'cyan')
            ->add(str_pad($next, 20), 'gray')
            ->add($status, $statusColor, 'bold')
            ->print();
    }
}
