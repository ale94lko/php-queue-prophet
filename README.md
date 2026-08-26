# php-queue-prophet

[![CI](https://github.com/ale94lko/php-queue-prophet/actions/workflows/ci.yml/badge.svg)](https://github.com/ale94lko/php-queue-prophet/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/ale94lko/php-queue-prophet/v)](https://packagist.org/packages/ale94lko/php-queue-prophet)
[![License](https://poser.pugx.org/ale94lko/php-queue-prophet/license)](https://packagist.org/packages/ale94lko/php-queue-prophet)

Lightweight, **zero-dependency**, framework-agnostic PHP library that predicts background worker memory leaks (OOM) and queue Time-to-Overflow (TTO) using ordinary least-squares linear regression over a fixed-size sliding window.

---

## Requirements

- PHP **8.1+**

## Installation

```bash
composer require ale94lko/php-queue-prophet
```

### Try it locally (playground)

```bash
cd examples/playground
composer install
composer demo
```

See [`examples/playground/README.md`](examples/playground/README.md) for memory-leak, stable-memory, and queue TTO demos.

---

## Features

- **Memory leak forecasting** — estimates remaining job iterations before a worker hits its memory limit
- **Queue Time-to-Overflow (TTO)** — projects when a backlog will breach capacity given arrival vs processing rates
- **Framework agnostic** — Vanilla PHP, Laravel Queue, Symfony Messenger, RoadRunner, Swoole, etc.
- **Zero runtime dependencies** — pure PHP math; the predictor itself cannot leak (bounded sliding window)
- **Strict typing** — PHPStan level 8, `declare(strict_types=1)` everywhere

---

## Quick start — worker memory health

```php
use PhpQueueProphet\WorkerHealthPredictor;

$predictor = new WorkerHealthPredictor(
    memoryLimit: '128M',   // or an int in bytes
    sampleWindowSize: 20,
);

while ($job = $queue->pop()) {
    $job->handle();

    $predictor->recordSample();
    $remaining = $predictor->predictRemainingJobs();

    // null  → not enough samples yet (< 3)
    // INF   → no leak detected (flat or shrinking memory)
    if ($remaining !== null && $remaining < 50) {
        // Gracefully stop / recycle the worker
        break;
    }
}
```

### Health report

```php
$report = $predictor->generateHealthReport();

$report->leakRatePerJobBytes;   // slope m (bytes per job)
$report->estimatedRemainingJobs;
$report->rSquared;              // fit quality [0, 1]
$report->isAtRisk(50);          // remaining jobs < threshold?
$report->getMemoryUsagePercent();
```

### Injecting samples (tests / custom collectors)

```php
$predictor->recordSample(memory_get_usage(true));
// or a custom value:
$predictor->recordSample(12_582_912);
```

---

## Quick start — queue Time-to-Overflow

```php
use PhpQueueProphet\QueueOverflowPredictor;

$tracker = new QueueOverflowPredictor(maxCapacity: 10_000);

// Rates in items/second (from your broker metrics, Redis LLEN deltas, etc.)
$tracker->record(
    currentDepth: 4_200,
    arrivalRate: 80.0,
    processingRate: 50.0,
);

$seconds = $tracker->predictTimeToOverflow();
// → 193.333…  ((10000 - 4200) / 30)

if ($tracker->getMetrics()->isAtRisk(300)) {
    // Scale workers, alert, shed load…
}
```

---

## Framework integration

The library has no framework bindings. Hook it where your workers already run.

### Laravel Queue

Record a sample after each job finishes (e.g. in your worker process or a listener):

```php
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use PhpQueueProphet\WorkerHealthPredictor;

// Typically registered once when the queue worker boots (custom worker command,
// AppServiceProvider, or a singleton bound in the container).
$predictor = new WorkerHealthPredictor(
    memoryLimit: ini_get('memory_limit') ?: '128M',
    sampleWindowSize: 20,
);

Event::listen(JobProcessed::class, function () use ($predictor): void {
    $predictor->recordSample();

    $remaining = $predictor->predictRemainingJobs();
    if ($remaining !== null && $remaining < 50) {
        // Ask the worker to exit after the current job so Supervisor/Octane restarts it.
        app('queue.worker')->shouldQuit = true;
    }
});
```

For queue depth / TTO, feed Redis (or your driver) metrics on a schedule:

```php
use Illuminate\Support\Facades\Redis;
use PhpQueueProphet\QueueOverflowPredictor;

$tracker = new QueueOverflowPredictor(maxCapacity: 50_000);

$depth = (int) Redis::llen('queues:default');
// Derive rates from your own counters / Horizon metrics / time deltas.
$tracker->record($depth, arrivalRate: 40.0, processingRate: 35.0);

if ($tracker->getMetrics()->isAtRisk(120)) {
    logger()->warning('Queue nearing capacity', (array) $tracker->getMetrics());
}
```

### Symfony Messenger

Use a middleware (or an event subscriber on `WorkerRunningEvent` / `WorkerMessageHandledEvent`):

```php
use PhpQueueProphet\WorkerHealthPredictor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class ProphetMemoryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly WorkerHealthPredictor $predictor,
        private readonly float $stopBelowJobs = 50.0,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        if ($envelope->last(HandledStamp::class) === null) {
            return $envelope;
        }

        $this->predictor->recordSample();
        $remaining = $this->predictor->predictRemainingJobs();

        if ($remaining !== null && $remaining < $this->stopBelowJobs) {
            // Signal your worker loop / Messenger stop strategy to shut down gracefully.
            throw new \Symfony\Component\Messenger\Exception\StopWorkerException(
                'Worker approaching OOM according to php-queue-prophet'
            );
        }

        return $envelope;
    }
}
```

Register the predictor as a shared service so the sliding window survives across messages in the same process:

```yaml
# config/services.yaml
PhpQueueProphet\WorkerHealthPredictor:
    arguments:
        $memoryLimit: '%env(default::memory_limit:MESSENGER_MEMORY_LIMIT)%'
        $sampleWindowSize: 20
```

---

## How it works

### Memory leak slope

Given \(N\) samples in the sliding window, where \(x_i\) is the sample index and \(y_i\) is memory in bytes:

\[
m = \frac{N\sum(xy) - \sum x\sum y}{N\sum(x^2) - (\sum x)^2}
\]

- \(m \le 0\) → stable or shrinking → `INF` (no OOM risk from leakage)
- \(m > 0\) → remaining jobs \(\approx (\text{limit} - \text{current}) / m\)

### Queue TTO

\[
\Delta = \text{arrival} - \text{processing},\quad
\text{TTO} = \frac{\text{capacity} - \text{depth}}{\Delta}
\]

- \(\Delta \le 0\) → draining/stable → `INF`
- Already at/over capacity → `0.0`

---

## API overview

| Class | Role |
|-------|------|
| `WorkerHealthPredictor` | Sliding-window memory leak predictor |
| `QueueOverflowPredictor` | Queue capacity overflow estimator |
| `Support\LinearRegression` | OLS slope, intercept, R² |
| `DTO\HealthReport` / `DTO\QueueMetrics` | Immutable snapshots |

Contracts live under `PhpQueueProphet\Contracts` for easy mocking and DI.

---

## Development

```bash
composer install
composer test           # PHPUnit
composer test-coverage  # PHPUnit + coverage (requires pcov or xdebug)
composer phpstan        # Level 8
composer cs-check       # PER Coding Style 2.0
composer check          # cs-check + phpstan + test
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for PR guidelines and [SECURITY.md](SECURITY.md) for vulnerability reporting.

CI runs on PHP 8.1, 8.2, 8.3, and 8.4.

---

## License

MIT © Fidel Alejandro Fernandez Arias
