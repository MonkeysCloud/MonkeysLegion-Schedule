<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\Schedule\Support\KeyNormalizer;

class KeyNormalizerTest extends TestCase
{
    public function testNormalizationAndDenormalization(): void
    {
        $testKeys = [
            'schedule:lock:check:uptime',
            'schedule_lock:check:uptime',
            'schedule_uclock',
            'foo_c_bar',
            '{}()/\@:',
            'foo_u_bar_c_baz_o_qux',
            'some:complex\\key{with}(all)/kinds@of_characters',
        ];

        foreach ($testKeys as $key) {
            $normalized = KeyNormalizer::normalize($key);
            
            // Check that the normalized key contains no PSR-16 reserved characters: {}()/\@:
            $this->assertDoesNotMatchRegularExpression('/[\{\}\(\)\/\\\@\:]/', $normalized);
            
            $denormalized = KeyNormalizer::denormalize($normalized);
            
            $this->assertSame($key, $denormalized, "Failed bidirectional conversion for key: $key");
        }
    }
}
