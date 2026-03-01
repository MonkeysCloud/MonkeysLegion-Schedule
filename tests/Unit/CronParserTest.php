<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Monkeyslegion\Schedule\Support\CronParser;
use DateTimeImmutable;
use DateTimeZone;

class CronParserTest extends TestCase
{
    private CronParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CronParser('UTC');
    }

    #[DataProvider('standardCronProvider')]
    public function testStandardCronExpressions(string $expression, string $dateTime, bool $shouldBeDue): void
    {
        $now = new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
        $this->assertEquals(
            $shouldBeDue,
            $this->parser->isDue($expression, $now),
            "Failed asserting that '$expression' is " . ($shouldBeDue ? 'due' : 'not due') . " at $dateTime"
        );
    }

    public static function standardCronProvider(): array
    {
        return [
            // 1. Every Minute
            ['* * * * *', '2026-03-01 10:00:00', true],
            ['* * * * *', '2026-03-01 10:05:45', true],

            // 2. Specific minute
            ['30 * * * *', '2026-03-01 10:30:00', true],
            ['30 * * * *', '2026-03-01 10:31:00', false],

            // 3. Hourly
            ['0 * * * *', '2026-03-01 11:00:00', true],
            ['0 * * * *', '2026-03-01 11:01:00', false],

            // 4. Daily
            ['30 14 * * *', '2026-03-01 14:30:00', true],
            ['30 14 * * *', '2026-03-01 14:31:00', false],

            // 5. Steps
            ['*/5 * * * *', '2026-03-01 10:00:00', true],
            ['*/5 * * * *', '2026-03-01 10:05:00', true],
            ['*/5 * * * *', '2026-03-01 10:04:00', false],

            // 6. Ranges
            ['0 1-3 * * *', '2026-03-01 01:00:00', true],
            ['0 1-3 * * *', '2026-03-01 02:00:00', true],
            ['0 1-3 * * *', '2026-03-01 04:00:00', false],

            // 7. Lists
            ['0,30 * * * *', '2026-03-01 10:00:00', true],
            ['0,30 * * * *', '2026-03-01 10:15:00', false],

            // 8. Weekly
            ['0 0 * * 0', '2026-03-01 00:00:00', true],
            ['0 0 * * 0', '2026-03-02 00:00:00', false],

            // 9. Monthly
            ['0 0 1 * *', '2026-03-01 00:00:00', true],

            // 10. Quarterly
            ['0 0 1 1,4,7,10 *', '2026-01-01 00:00:00', true],
            ['0 0 1 1,4,7,10 *', '2026-03-01 00:00:00', false],
        ];
    }

    #[DataProvider('subMinuteCronProvider')]
    public function testSubMinuteCronExpressions(string $expression, string $dateTime, bool $shouldBeDue): void
    {
        $now = new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
        $this->assertEquals(
            $shouldBeDue,
            $this->parser->isDue($expression, $now),
            "Failed asserting that sub-minute '$expression' is " . ($shouldBeDue ? 'due' : 'not due') . " at $dateTime"
        );
    }

    public static function subMinuteCronProvider(): array
    {
        return [
            ['* * * * * *', '2026-03-01 10:00:05', true],
            ['30 * * * * *', '2026-03-01 10:00:30', true],
            ['30 * * * * *', '2026-03-01 10:00:31', false],
            ['*/2 * * * * *', '2026-03-01 10:00:00', true],
            ['*/2 * * * * *', '2026-03-01 10:00:02', true],
            ['*/2 * * * * *', '2026-03-01 10:00:03', false],
            ['0 0 10 * * *', '2026-03-01 10:00:00', true],
        ];
    }

    public function testTimezoneHandling(): void
    {
        $nowUtc = new DateTimeImmutable('2026-03-01 14:30:00', new DateTimeZone('UTC'));
        $tokyoParser = new CronParser('Asia/Tokyo');
        $this->assertTrue($tokyoParser->isDue('30 23 * * *', $nowUtc));
        $this->assertFalse($tokyoParser->isDue('30 14 * * *', $nowUtc));
    }

    public function testNextRunDateCalculatedCorrectly(): void
    {
        $expression = '0 0 * * *';
        $next = $this->parser->nextRun($expression);
        $this->assertInstanceOf(DateTimeImmutable::class, $next);
        $this->assertEquals('00:00', $next->format('H:i'));
    }
}
