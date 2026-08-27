# php-queue-prophet playground

A small local project to try the library without Laravel or Symfony.

## Requirements

- PHP 8.1+
- Composer

## Setup

From this directory:

```bash
cd examples/playground
composer install
```

Composer links the parent package (`ale94lko/php-queue-prophet`) via a path repository.

## Run

```bash
# All three demos
composer demo

# Memory leak → OOM forecast
composer demo:memory

# Stable memory → INF (no risk)
composer demo:stable

# Queue Time-to-Overflow
composer demo:queue
```

Or directly:

```bash
php bin/demo.php
php bin/demo.php memory
php bin/demo.php stable
php bin/demo.php queue
```

## What you will see

| Demo | What it shows |
|------|----------------|
| `memory` | Worker “leaking” ~256 KB/job; the predictor marks `STOP` before the limit |
| `stable` | Flat memory → `remaining = INF` |
| `queue` | Arrival vs processing scenarios and TTO |

## Experiment

Edit the classes under `src/`:

- `MemoryLeakDemo`: `$leakPerJobBytes`, `$stopBelowJobs`, memory limit
- `QueueOverflowDemo`: capacity and `in` / `out` rates

Then run `composer demo` again.
