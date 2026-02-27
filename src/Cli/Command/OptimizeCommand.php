<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use Monkeyslegion\Schedule\Discovery\AttributeScanner;
use MonkeysLegion\Database\Cache\Contracts\CacheInterface;

#[CommandAttr('schedule:optimize', 'The Optimizer. Cache all the scheduled tasks in the Cache Provider for faster retrieval.')]
final class OptimizeCommand extends Command
{
    private readonly AttributeScanner $scanner;
    public function __construct(
        private readonly CacheInterface $cache
    ) {
        parent::__construct();
        $this->scanner = new AttributeScanner();
    }

    /**
     * Handle the optimization process.
     * Scans for all [Scheduled] attributes and persists the resulting Task objects to cache.
     */
    protected function handle(): int
    {
        $this->info('Starting Schedule Optimizer...');
        
        try {
            $this->line('Scanning for scheduled tasks...');
            $tasks = $this->scanner->scan();
            
            $count = count($tasks);
            $this->info("Found {$count} scheduled tasks.");

            if ($count === 0) {
                $this->warning('No tasks found to cache.');
                return self::SUCCESS;
            }

            $this->line('Persisting tasks to cache...');
            
            // We use a fixed key 'schedule:tasks' for the cached tasks.
            $this->cache->set('schedule:tasks', $tasks, 86400 * 365); // Cache for a year

            $this->info('Optimization complete! All tasks have been cached.');
            
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Optimization failed: ' . $e->getMessage());
            if ($this->hasOption('v') || $this->hasOption('verbose')) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}
