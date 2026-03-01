<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Traits;

trait ManagesFrequencies
{
    /**
     * High-Precision Frequencies (For Daemon Mode)
     */

    public function everySecond(): self
    {
        return $this->cron('* * * * * *'); // 6th digit extension
    }

    public function everyTwoSeconds(): self
    {
        return $this->cron('*/2 * * * * *');
    }

    public function everyThirtySeconds(): self
    {
        return $this->cron('*/30 * * * * *');
    }

    /**
     * Minute Frequencies
     */

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): self
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): self
    {
        return $this->cron('0,30 * * * *');
    }

    /**
     * Hourly Frequencies
     */

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int|array $minutes): self
    {
        $value = is_array($minutes) ? implode(',', $minutes) : (string) $minutes;
        return $this->spliceIntoPosition(1, $value);
    }

    public function everyTwoHours(): self
    {
        return $this->cron('0 */2 * * *');
    }

    /**
     * Daily Frequencies
     */

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        return $this->at($time);
    }

    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron("0 $first,$second * * *");
    }

    /**
     * Weekly Frequencies
     */

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int|array $days, string $time = '00:00'): self
    {
        $this->dailyAt($time);
        $value = is_array($days) ? implode(',', $days) : (string) $days;
        return $this->spliceIntoPosition(5, $value);
    }

    public function weekdays(): self
    {
        return $this->spliceIntoPosition(5, '1-5');
    }

    public function weekends(): self
    {
        return $this->spliceIntoPosition(5, '0,6');
    }

    public function mondays(): self
    {
        return $this->weeklyOn(1);
    }
    public function tuesdays(): self
    {
        return $this->weeklyOn(2);
    }
    public function wednesdays(): self
    {
        return $this->weeklyOn(3);
    }
    public function thursdays(): self
    {
        return $this->weeklyOn(4);
    }
    public function fridays(): self
    {
        return $this->weeklyOn(5);
    }
    public function saturdays(): self
    {
        return $this->weeklyOn(6);
    }
    public function sundays(): self
    {
        return $this->weeklyOn(0);
    }

    /**
     * Monthly Frequencies
     */

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $day = 1, string $time = '00:00'): self
    {
        $this->dailyAt($time);
        return $this->spliceIntoPosition(3, (string) $day);
    }

    public function twiceMonthly(int $first = 1, int $second = 16, string $time = '00:00'): self
    {
        $this->dailyAt($time);
        return $this->spliceIntoPosition(3, "$first,$second");
    }

    public function lastDayOfMonth(string $time = '00:00'): self
    {
        // Note: Standard Cron doesn't support 'L', usually handled in the Runner via date('t')
        $this->dailyAt($time);
        return $this->spliceIntoPosition(3, '28-31');
    }

    /**
     * Yearly & Quarterly
     */

    public function quarterly(): self
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    public function yearlyOn(int $month = 1, int $day = 1, string $time = '00:00'): self
    {
        $this->dailyAt($time);
        return $this->spliceIntoPosition(3, (string) $day)
            ->spliceIntoPosition(4, (string) $month);
    }

    /**
     * Core Helpers
     */

    public function at(string $time): self
    {
        $parts = explode(':', $time);
        $minute = (int) ($parts[1] ?? 0);
        $hour = (int) ($parts[0] ?? 0);

        return $this->spliceIntoPosition(1, (string) $minute)
            ->spliceIntoPosition(2, (string) $hour);
    }

    public function cron(string $expression): self
    {
        $this->expression = $expression;
        return $this;
    }

    protected function spliceIntoPosition(int $position, string $value): self
    {
        $segments = explode(' ', $this->expression);
        $count = count($segments);

        // If we have 6 fields (sec min hour day month dow), 
        // and we are targeting min (1), hour (2) etc. from the 5-field logic,
        // we need to shift the index by 1.
        if ($count === 6 && $position >= 1 && $position <= 5) {
            $actualPosition = $position; // In 6-field: sec(0) min(1) hour(2) day(3) month(4) dow(5)
            // Wait, if position is 1 (minute) in 5-field: min(0) hour(1) day(2) month(3) dow(4)
            // So if 6-field: sec(0) min(1)...
            // The position passed is usually 1-5.
            $segments[$actualPosition] = $value;
        } else {
            $segments[$position - 1] = $value;
        }

        $this->expression = implode(' ', $segments);
        return $this;
    }
}
