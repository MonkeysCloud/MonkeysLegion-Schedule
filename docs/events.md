# Task Events & Lifecycle

MonkeysLegion-Schedule provides a robust event system to monitor and react to task execution.

## System-Wide Events

The following events are dispatched by the `Schedule` instance during execution:

| Event | Dispatched When |
|-------|-----------------|
| `Monkeyslegion\Schedule\Events\TaskStarting` | Just before a task is executed. |
| `Monkeyslegion\Schedule\Events\TaskFinished` | After a task completes successfully. |
| `Monkeyslegion\Schedule\Events\TaskFailed` | When a task throws an exception or returns a non-zero exit code. |

### Listening to Events

You can register listeners globally via the `Schedule` instance:

```php
use Monkeyslegion\Schedule\Events\TaskStarting;
use Monkeyslegion\Schedule\Events\TaskFailed;

$schedule->listen(TaskStarting::class, function (TaskStarting $event) {
    Log::info("Task starting: " . $event->task->id);
});

$schedule->listen(TaskFailed::class, function (TaskFailed $event) {
    Log::error("Task failed: " . $event->task->id . " Error: " . $event->exception->getMessage());
});
```

---

## Task-Specific Lifecycle Callbacks (Handlers)

If you only need to react to a specific task, you can use the fluent API with **Closures** or **Invokable Handler Classes**.

### Using a Closure

```php
$schedule->command('backup:database')
    ->onSuccess(fn(Task $task, $result) => print("Backup complete!\n"));
```

### Using an Invokable Class (The "Task Handler")

If your logic is complex, create a dedicated handler class.

```php
namespace App\Handlers;

use Monkeyslegion\Schedule\Task;

class DatabaseBackupHandler
{
    public function __invoke(Task $task, mixed $result)
    {
        // Complex logic here: logging, notifications, cleanup.
    }
}

// In your schedule definition:
$schedule->command('backup:database')
    ->onSuccess(new \App\Handlers\DatabaseBackupHandler());
```

---

## Custom Event Objects Per Task

You can specify custom event classes to be dispatched for specific tasks. This is useful for complex monitoring or integration with other system components.

### 1. Define Your Custom Event

Custom events must accept the same constructor arguments as the defaults.

```php
namespace App\Events;

use Monkeyslegion\Schedule\Task;

class SyncStarted
{
    public function __construct(public Task $task) {}
}
```

### 2. Register via Attributes

```php
use App\Events\SyncStarted;
use App\Events\SyncFinished;

#[Scheduled(
    expression: '0 0 * * *',
    startingEvent: SyncStarted::class,
    finishedEvent: SyncFinished::class
)]
class DatabaseSync { ... }
```

### 3. Register via Fluent API

```php
$task = $schedule->command('sync:data');
$task->startingEvent = SyncStarted::class;
```

When this task starts, it will dispatch `App\Events\SyncStarted` instead of the generic `TaskStarting`.

---

## Task Metadata

You can attach arbitrary metadata or context to a task, which will be available in all lifecycle callbacks and event listeners.

```php
$schedule->command('report:generate')
    ->setMetadata('tenant_id', 123)
    ->onStart(function(Task $task) {
        $tenantId = $task->metadata['tenant_id'];
        // Do something with tenant context
    });
```
