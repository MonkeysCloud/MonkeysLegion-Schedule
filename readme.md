# MonkeysLegion Schedule

> A high-performance, modular task scheduling ecosystem for PHP 8.4+.

[![Latest Version](https://img.shields.io/badge/version-dev-blue.svg?style=flat-square)](https://github.com/monkeyscloud/monkeyslegion-schedule)
[![PHP Version](https://img.shields.io/badge/php-%5E8.4-777bb4.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)

---

## ✨ Overview

**MonkeysLegion Schedule** is a modern scheduling engine designed for high reactivity and developer happiness. It bridges the gap between traditional Crontab one-shots and modern reactive daemons, supporting both **Attribute-based Discovery** and a **Fluent API**.

### 🚀 Key Features

- 🧩 **Hybrid Discovery**: Automatic class-scanning via Attributes or manual registration via Service Providers.
- ⚡ **High Reactivity**: Support for **Sub-Minute (Second-Level)** precision.
- 👹 **Daemon Mode**: A persistent worker loop that polls for "pushed" ad-hoc tasks.
- 🧵 **Multi-Process execution**: Task isolation using `proc_open` to prevent bottlenecks.
- 📡 **Lifecycle Events**: Global event dispatching and per-task fluent callbacks (`onStart`, `onSuccess`, etc.).
- 📦 **Driver Agnostic**: State persistence and task queuing via **Redis** or **Cache**.

---

## 🛠 Installation

```bash
composer require monkeyscloud/monkeyslegion-schedule
```

---

## 📖 Core Concepts

### 1. Task Registration

#### **Attribute-Based (Declarative)**

Just add the `#[Scheduled]` attribute to any class with an `__invoke` method or to specific class methods.

```php
use Monkeyslegion\Schedule\Attributes\Scheduled;

#[Scheduled(expression: '* * * * * *')] // Second-precision!
class HeartbeatAction
{
    public function __invoke()
    {
        // Runs every second
    }
}
```

#### **Manual Registration (Dynamic)**

Ideal for Closures or raw CLI commands registered inside your Service Providers.

```php
$schedule->command('report:generate --daily')->dailyAt('00:00');

$schedule->call(function() {
    return "Pulse sent.";
})->everyFiveMinutes();
```

### 2. Execution Modes

| Mode | Command | Frequency | Best For |
| :--- | :--- | :--- | :--- |
| **Normal** | `php ml schedule:run` | Run once per execution | Standard system maintenance. |
| **Daemon** | `php ml schedule:work` | Continuous (1s Pulse) | Real-time tasks & Ad-hoc pushed jobs. |

---

## 🎮 CLI Command Palette

| Command | Description |
| :--- | :--- |
| `schedule:run` | **The Heartbeat.** Execute all tasks that are currently due. |
| `schedule:work` | **The Daemon.** Persistent loop with 1s pulse and Redis polling. |
| `schedule:optimize` | **The Cache.** Warm up task discovery for production performance. |
| `schedule:list` | **The Dashboard.** View upcoming tasks and their status (Not yet implemented). |
| `schedule:test {id}` | **The Sandbox.** Manually trigger a specific task for debugging (Not yet implemented). |

---

## 🔔 Events & Callbacks

Monitor your ecosystem with high-level dispatcher events or task-specific hooks.

- **System Events**: `TaskStarting`, `TaskFinished`, `TaskFailed`.
- **Fluent Hooks**: `->onStart()`, `->onSuccess()`, `->onFailure()`, `->after()`.

> [!TIP]
> Check the full [Events Documentation](docs/events.md) for custom event object overrides and metadata handling.

---

## 🗺 Roadmap

### Phase 4: Atomic Locking (In Progress) ⏳

- [ ] **LockProvider**: Interfacing with Redis for distributed locking.
- [ ] **Overlapping Prevention**: `->withoutOverlapping()` logic to ensure task exclusivity.
- [ ] **Self-Healing**: TTL-based lock expiry to handle process crashes.

### Completed Milestones ✅

- [x] **Core Architecture**: Task Value Objects and the Registry.
- [x] **Discovery Engine**: Attribute scanning and dynamic loading.
- [x] **Sub-Minute Precision**: 6-segment Cron support.
- [x] **Multi-Process isolation**: Non-blocking task execution.
- [x] **Redis Integration**: Pushed ad-hoc task support via Redis drivers.
- [x] **Logger & Monitoring**: Seamless `monkeyslegion/logger` integration.

---

## 🤝 Contributing

We welcome monkeys from all forests! Please read our [Contributing Guide](CONTRIBUTING.md) before submitting PRs.

---

## ⚖️ License

Developed by the **MonkeysCloud Team**. Released under the [MIT License](LICENSE).
