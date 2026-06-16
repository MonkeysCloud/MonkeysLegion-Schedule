<?php

declare(strict_types=1);

namespace MonkeysLegion\Schedule\Support;

class KeyNormalizer
{
    /**
     * Map of unsafe PSR-16 cache key characters to safe strings.
     * We also map the escape character '_' itself to prevent collision/ambiguity.
     */
    private static array $normalizeMap = [
        '_' => '_u',
        ':' => '_c',
        '{' => '_o',
        '}' => '_d',
        '(' => '_p',
        ')' => '_q',
        '/' => '_s',
        '\\' => '_b',
        '@' => '_a',
    ];

    /**
     * Normalize a cache key by replacing reserved characters with safe representation.
     */
    public static function normalize(string $key): string
    {
        return strtr($key, self::$normalizeMap);
    }

    /**
     * Restore a normalized cache key back to its original representation.
     */
    public static function denormalize(string $normalizedKey): string
    {
        return strtr($normalizedKey, array_flip(self::$normalizeMap));
    }
}
