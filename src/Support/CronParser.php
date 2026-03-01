<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Support;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;

class CronParser
{
    public function __construct(
        private readonly string $timezone = 'UTC'
    ) {}

    public function isDue(string $expression, ?DateTimeImmutable $now = null): bool
    {
        $date = $now ?? new DateTimeImmutable('now', new DateTimeZone($this->timezone));
        $date = $date->setTimezone(new DateTimeZone($this->timezone));
        $parts = explode(' ', trim($expression));

        // Sub-minute support (6 segments: sec min hour day month dow)
        if (count($parts) === 6) {
            $secondPart = array_shift($parts);
            $expression = implode(' ', $parts);

            // Enhanced second check
            $currentSecond = (int) $date->format('s');
            if ($secondPart === '*') {
                // all seconds
            } elseif (str_starts_with($secondPart, '*/')) {
                $step = (int) substr($secondPart, 2);
                if ($currentSecond % $step !== 0) {
                    return false;
                }
            } elseif ((int)$secondPart !== $currentSecond) {
                return false;
            }
        }

        // Standard Cronoperates on 1-minute precision
        $currentTime = $date->format('Y-m-d H:i:00');

        return (new CronExpression($expression))->isDue($currentTime);
    }

    /**
     * Get the next run date for a given expression (Great for schedule:list).
     */
    public function nextRun(string $expression): DateTimeImmutable
    {
        $cron = new CronExpression($expression);
        $next = $cron->getNextRunDate('now', 0, false, $this->timezone);

        return DateTimeImmutable::createFromMutable($next);
    }
}
