<?php
/**
 * Dummy entrypoint for testing framework-wrapped commands.
 */
$args = array_slice($argv, 1);

if (empty($args)) {
    exit(0);
}

// Simple echo implementation for unit tests
if ($args[0] === 'echo') {
    $output = str_replace('"', '', $args[1] ?? '');
    echo $output;
    exit(0);
}

// Fallback: exit with error if unknown
exit(1);
