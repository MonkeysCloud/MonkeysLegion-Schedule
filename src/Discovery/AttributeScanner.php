<?php

declare(strict_types=1);

namespace MonkeysLegion\Scheduler\Discovery;

use ReflectionClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use MonkeysLegion\Scheduler\Task;
use MonkeysLegion\Scheduler\Attributes\Scheduled;

class AttributeScanner
{
    public function __construct(
        private string $scanPath = 'app' // Default folder to scan
    ) {}

    /**
     * @return array<Task>
     */
    public function scan(): array
    {
        $tasks = [];
        $directory = base_path($this->scanPath);

        if (!is_dir($directory)) {
            return [];
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

        return $tasks;
    }

    private function getTasksFromClass(string $className): array
    {
        $found = [];
        $reflection = new ReflectionClass($className);

        // 1. Check Class Level Attributes
        $classAttributes = $reflection->getAttributes(Scheduled::class);
        foreach ($classAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $found[] = new Task(
                action: [$className, '__invoke'], // Assume invokable
                expression: $instance->expression,
                tags: $instance->tags,
                withoutOverlapping: !$instance->overlap,
                ttl: $instance->ttl ?? 3600
            );
        }

        // 2. Check Method Level Attributes
        foreach ($reflection->getMethods() as $method) {
            $methodAttributes = $method->getAttributes(Scheduled::class);
            foreach ($methodAttributes as $attribute) {
                $instance = $attribute->newInstance();
                $found[] = new Task(
                    action: [$className, $method->getName()],
                    expression: $instance->expression,
                    tags: $instance->tags,
                    withoutOverlapping: !$instance->overlap,
                    ttl: $instance->ttl ?? 3600
                );
            }
        }

        return $found;
    }

    private function extractClassName(string $filePath): ?string
    {
        return str_replace(
            ['/', '.php'],
            ['\\', ''],
            str_replace(base_path() . '/', '', $filePath)
        );
    }
}