<?php

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return __DIR__ . '/Fixtures/Scanner/' . $path;
    }
}

require __DIR__ . '/../vendor/autoload.php';
