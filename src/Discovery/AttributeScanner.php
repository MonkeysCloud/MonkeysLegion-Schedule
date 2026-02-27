<?php

declare(strict_types=1);

namespace Monkeyslegion\Schedule\Discovery;

use ReflectionClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Monkeyslegion\Schedule\Task;
use Monkeyslegion\Schedule\Attributes\Scheduled;

class AttributeScanner
{
    /**
     * @param array<string> $scanPaths Default folders to scan
     */
    public function __construct(
        private array $scanPaths = ['app'] // Default folder to scan
    ) {}

    /**
     * @return array<Task>
     */
    public function scan(): array
    {
        $tasks = [];
        
        foreach ($this->scanPaths as $path) {
            $directory = base_path($path);

            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->extractClassName($file->getPathname());
                if (!$className || !class_exists($className)) {
                    continue;
                }

                $tasks = array_merge($tasks, $this->getTasksFromClass($className));
            }
        }

        return $tasks;
    }

    private function getTasksFromClass(string $className): array
    {
        $found = [];
        $reflection = new ReflectionClass($className);

        // 1. Check Class Level Attributes
        foreach ($reflection->getAttributes(Scheduled::class) as $attribute) {
            $instance = $attribute->newInstance();
            $found[] = new Task(
                action: [$className, '__invoke'], // Assume invokable
                expression: $instance->expression,
                tags: $instance->tags,
                withoutOverlapping: !$instance->overlap,
                ttl: $instance->ttl ?? 3600,
                onOneServer: $instance->onOneServer
            );
        }

        // 2. Check Method Level Attributes
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Scheduled::class) as $attribute) {
                $instance = $attribute->newInstance();
                $found[] = new Task(
                    action: [$className, $method->getName()],
                    expression: $instance->expression,
                    tags: $instance->tags,
                    withoutOverlapping: !$instance->overlap,
                    ttl: $instance->ttl ?? 3600,
                    onOneServer: $instance->onOneServer
                );
            }
        }

        return $found;
    }

    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        if (!preg_match('/namespace\s+([^;]+);/S', $content, $namespaceMatches)) {
            return null;
        }

        $namespace = trim($namespaceMatches[1]);

        if (!preg_match('/(?:class|readonly class)\s+([^\s{]+)/S', $content, $classMatches)) {
            return null;
        }

        return $namespace . '\\' . $classMatches[1];
    }
}