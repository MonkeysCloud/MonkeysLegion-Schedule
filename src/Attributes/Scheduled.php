<?php

declare(strict_types=1);

namespace MonkeysLegion\Scheduler\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Scheduled
{
    /**
     * @param string $expression The Cron expression (e.g., "* * * * *")
     * @param array<string> $tags Categorize tasks for filtering in CLI
     * @param bool $onOneServer Prevent multi-server execution (Shared Lock)
     * @param bool $overlap Allow or prevent overlapping instances
     * @param int|null $ttl Custom lock timeout in seconds
     */
    public function __construct(
        public string $expression,
        public array $tags = [],
        public bool $onOneServer = false,
        public bool $overlap = false,
        public ?int $ttl = null,
    ) {}
}