# MonkeysLegion Framework: Task Scheduler Ecosystem Planning

This document outlines the architectural and functional plan for the `monkeyslegion/scheduler` package, designed for PHP 8.4+ modular ecosystems.

---

## 1. Task Registration & Discovery (Hybrid Model)

The scheduler uses a dual-entry approach to balance modern declarative programming with dynamic flexibility.

* **Attribute Discovery:** The framework auto-scans classes for the `#[Scheduled]` attribute. This is the primary method for modular, class-based tasks.
* **Manual Registration:** A fluent API (`->call()`, `->command()`) is provided within Service Providers or Loaders for registering **Closures** and raw CLI strings.
* **Attributes on Closures:** Supported for metadata, though manual registration remains the primary way to "announce" them to the engine.
* **Location:** Registration logic is decoupled from the Router, living within the Scheduler's own lifecycle (hooking into the `onEnable` phase).

---

## 2. The Execution Engine (Hybrid Model)

The "Runner" handles how and when tasks are physically triggered.

* **Modes:**
  * **Normal Mode:** One-shot execution (`schedule:run`) typically triggered by a system-level Crontab every minute.
  * **Daemon Mode:** A persistent loop (`schedule:work`) using `while(true)` and `sleep(1)`.
* **Reactivity:** In Daemon mode, the engine polls drivers (Redis/DB) for "pushed" or ad-hoc tasks during the sleep cycle for near-instant execution.
* **Concurrency:** **Multi-process spawning** is used to ensure the heartbeat remains consistent. Long-running tasks will not block the discovery or execution of subsequent tasks.

---

## 3. Atomic Locking & Overlap Prevention

Leveraging the framework's core "Atomic Operations" philosophy to prevent race conditions.

* **Default State:** **"No Overlap"** is enabled by default for all tasks. Developers must explicitly opt-out via `->allowOverlapping()`.
* **Mechanism:** Uses the `monkeyslegion` Cache/Atomic Provider.
* **Scope:** Shared locks (Redis/Database) are prioritized for multi-server clusters; local file locks serve as a fallback for single-server setups.
* **Resilience (Zombie Prevention):** Implements a **TTL-based strategy**. A sensible fixed TTL (e.g., 1 hour) ensures that if a process crashes, the lock eventually expires, allowing the task to resume in future cycles.

---

## 4. Persistence & Monitoring (The State)

Tracking the health and history of the background ecosystem.

* **Logging:** Captures `stdout` and `stderr` using the internal `monkeyslegion/logger`.
* **State Management:** Minimalist metadata (Last Run Time, Success/Failure status) is persisted in the Cache Provider for quick CLI retrieval.
* **Hooks & Events:** Provides a full suite of lifecycle hooks:
  * `before()`: Setup logic.
  * `onSuccess()`: Logic triggered on exit code 0.
  * `onFailure()`: Alerts (Slack/Email) or fallback logic.
  * `after()`: Final cleanup.

---

## 5. CLI Integration & Developer Tooling

Integrated commands provided via the `monkeyslegion/cli` package.

-----------------------------------------------------------------------------------------------------------------------------------------------------------------
| Command               | Arguments/Options     | Description                                                                                                   |
| :---------------------| :---------------------| :-------------------------------------------------------------------------------------------------------------|
| `schedule:run`        | `--force`, `--tag=`   | **The Heartbeat.** One-shot execution. Evaluates the clock and spawns sub-processes for due tasks.            |
| `schedule:work`       | `--sleep=1`           | **The Daemon.** Persistent loop. Polls system clock and Drivers (Redis/DB) for high reactivity.               |
| `schedule:list`       | `--verbose`           | **The Dashboard.** Displays a table of all tasks with human-friendly frequencies and "Next Run" countdowns.   |
| `schedule:test`       | `{task_id}`           | **The Sandbox.** Manually triggers a task immediately. Respects locks unless `--force` is used.               |
| `schedule:clear-locks`| `{task_id?}`          | **The Emergency Brake.** Manually clears active Atomic Locks from the Cache.                                  |
| `schedule:interrupt`  | `{pid}`               | **Process Control.** Safely stops a running sub-process without killing the main Daemon loop.                 |
-----------------------------------------------------------------------------------------------------------------------------------------------------------------
