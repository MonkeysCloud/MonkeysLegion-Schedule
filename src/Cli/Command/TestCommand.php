<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Cli\Command;

use Exception;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\Schedule\Schedule;

#[CommandAttr(
    'schedule:test',
    'Manually trigger a specific task for debugging.'
)]
final class TestCommand extends Command
{
    public function __construct(
        private readonly Schedule $schedule
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $id = $this->argument(0); // Assuming first arg after script name, actually it might be second after command name?
        // Command.php::argument(0) => argv[commandIndex + 1 + 0]
        // argv[0] = script, argv[1] = command, argv[2] = arg0
        // So argument(0) is correct for the first argument after command.

        if ($id === null) {
            $this->cliLine()
                ->add('Error: ', 'red', 'bold')
                ->add('Task ID required. Usage: ')
                ->add('schedule:test {id}', 'yellow')
                ->print();
            return self::FAILURE;
        }

        $task = $this->schedule->getTask($id);

        if ($task === null) {
            $this->cliLine()
                ->add('Error: ', 'red', 'bold')
                ->add('Task not found: ')
                ->add($id, 'yellow')
                ->print();
            return self::FAILURE;
        }

        $this->cliLine()
            ->add('Execution started: ', 'cyan')
            ->add($task->id, 'white', 'bold')
            ->print();

        try {
            $task->dispatchStarting($this->schedule);
            $result = $task->execute();
            $task->dispatchFinished($this->schedule, $result);

            $this->cliLine()
                ->success('Execution finished. ')
                ->add('Result: ', 'gray')
                ->add(print_r($result, true), 'white')
                ->print();
        } catch (Exception $e) {
            $task->dispatchFailed($this->schedule, $e);
            $this->cliLine()
                ->error('Execution failed: ')
                ->add($e->getMessage(), 'white')
                ->print();
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
