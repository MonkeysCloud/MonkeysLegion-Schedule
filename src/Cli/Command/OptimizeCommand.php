<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use Monkeyslegion\Schedule\Discovery\AttributeScanner;
use Monkeyslegion\Database\Cache\Contracts\CacheInterface;
use Monkeyslegion\Schedule\Task;

#[CommandAttr(
    'schedule:optimize',
    'Cache all scheduled tasks for faster retrieval.'
)]
final class OptimizeCommand extends Command
{
    private readonly AttributeScanner $scanner;

    public function __construct(
        private readonly CacheInterface $cache
    ) {
        parent::__construct();
        $this->scanner = new AttributeScanner();
    }

    protected function handle(): int
    {
        $this->workerLine('OPTIMIZER STARTED', 'green');

        try {
            $tasks = $this->scanner->scan();
            $count = count($tasks);

            if ($count === 0) {
                $this->workerLine('No tasks found to cache.', 'yellow');
                return self::SUCCESS;
            }

            $this->workerLine("Persisting {$count} tasks to cache...", 'cyan');

            $this->cache->set(Task::CACHE_KEY_TASKS, $tasks, 86400 * 365); // 1 year TTL

            $this->workerLine('Optimization complete! All tasks have been cached.', 'green');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->workerLine('OPTIMIZATION FAILED: ' . $e->getMessage(), 'red');

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

    private function isVerbose(): bool
    {
        return $this->hasOption('v') || $this->hasOption('verbose');
    }
}
