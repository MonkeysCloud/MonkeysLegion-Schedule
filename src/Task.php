<?php

declare(strict_types=1);

namespace MonkeysLegion\Scheduler;

use Closure;

readonly class Task
{
    public string $id;

    public function __construct(
        /** The "Payload": can be a string 'php ml wipe:cache', a Closure, or [ClassName::class, 'method'] */
        public string|Closure|array $action,
        
        /** The Cron Expression parsed from the Attribute or Fluent Builder */
        public string $expression,
        
        /** Metadata for the CLI schedule:list command */
        public array $tags = [],
        
        /** Configuration for Point 3 (Locking) */
        public bool $withoutOverlapping = true,
        public int $ttl = 3600,
        
        /** Custom name (Optional, defaults to a hash of the action) */
        ?string $name = null 
    ) {
        $this->id = $name ?? $this->generateId($action);
    }

    private function generateId(mixed $action): string
    {
        // Creates a unique fingerprint so the Atomic Lock knows this specific task
        if (is_string($action)) return md5($action);
        if ($action instanceof Closure) return 'closure_' . spl_object_hash($action);
        if (is_array($action)) return md5(serialize($action));
        
        return uniqid('task_', true);
    }
}