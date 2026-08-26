<?php

declare(strict_types=1);

namespace PhpQueueProphet;

use InvalidArgumentException;
use PhpQueueProphet\Contracts\PredictorInterface;
use PhpQueueProphet\DTO\HealthReport;
use PhpQueueProphet\Exceptions\InsufficientDataException;
use PhpQueueProphet\Exceptions\InvalidMemoryLimitException;
use PhpQueueProphet\Support\LinearRegression;

final class WorkerHealthPredictor implements PredictorInterface
{
    private const MIN_SAMPLES_FOR_PREDICTION = 3;

    /** @var list<int> Fixed-size sliding window of memory samples in bytes. */
    private array $memorySamples = [];

    private readonly int $memoryLimitBytes;

    public function __construct(
        string|int $memoryLimit = '128M',
        private readonly int $sampleWindowSize = 20,
        private readonly bool $triggerGarbageCollection = true,
    ) {
        if ($this->sampleWindowSize < self::MIN_SAMPLES_FOR_PREDICTION) {
            throw new InvalidArgumentException(sprintf(
                'Sample window size must be at least %d.',
                self::MIN_SAMPLES_FOR_PREDICTION,
            ));
        }

        $this->memoryLimitBytes = is_int($memoryLimit)
            ? $memoryLimit
            : $this->parseBytes($memoryLimit);

        if ($this->memoryLimitBytes <= 0) {
            throw new InvalidMemoryLimitException('Memory limit must be greater than zero.');
        }
    }

    public function recordSample(?int $memoryBytes = null): void
    {
        if ($memoryBytes !== null && $memoryBytes < 0) {
            throw new InvalidArgumentException('Memory sample must be a non-negative integer.');
        }

        if ($this->triggerGarbageCollection && $memoryBytes === null) {
            gc_collect_cycles();
        }

        $this->memorySamples[] = $memoryBytes ?? memory_get_usage(true);

        if (count($this->memorySamples) > $this->sampleWindowSize) {
            array_shift($this->memorySamples);
        }
    }

    public function predictRemainingJobs(): ?float
    {
        $count = count($this->memorySamples);
        if ($count < self::MIN_SAMPLES_FOR_PREDICTION) {
            return null;
        }

        $slope = $this->regression()->slope();

        if ($slope <= 0.0) {
            return INF;
        }

        $currentMemory = $this->memorySamples[$count - 1];
        $remainingBytes = $this->memoryLimitBytes - $currentMemory;

        if ($remainingBytes <= 0) {
            return 0.0;
        }

        return $remainingBytes / $slope;
    }

    /**
     * Like predictRemainingJobs(), but throws when data is insufficient.
     *
     * @throws InsufficientDataException
     */
    public function predictRemainingJobsOrFail(): float
    {
        $result = $this->predictRemainingJobs();

        if ($result === null) {
            throw new InsufficientDataException(sprintf(
                'At least %d samples are required to predict remaining jobs; %d given.',
                self::MIN_SAMPLES_FOR_PREDICTION,
                count($this->memorySamples),
            ));
        }

        return $result;
    }

    public function generateHealthReport(): HealthReport
    {
        $count = count($this->memorySamples);
        $currentMemory = $count > 0
            ? $this->memorySamples[$count - 1]
            : memory_get_usage(true);

        $regression = $this->regression();

        return new HealthReport(
            currentMemoryBytes: $currentMemory,
            memoryLimitBytes: $this->memoryLimitBytes,
            leakRatePerJobBytes: $regression->slope(),
            estimatedRemainingJobs: $this->predictRemainingJobs(),
            sampleCount: $count,
            rSquared: $regression->rSquared(),
        );
    }

    public function getSampleCount(): int
    {
        return count($this->memorySamples);
    }

    public function getMemoryLimitBytes(): int
    {
        return $this->memoryLimitBytes;
    }

    /**
     * Resets the sliding window. Useful between worker restarts in long-lived processes.
     */
    public function reset(): void
    {
        $this->memorySamples = [];
    }

    private function regression(): LinearRegression
    {
        return new LinearRegression($this->memorySamples);
    }

    /**
     * Parses PHP-style memory size strings (e.g. "128M", "1G", "512K", "128MB").
     */
    private function parseBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || !preg_match('/^(-?\d+(?:\.\d+)?)\s*([kmgt]?b?)?$/i', $value, $matches)) {
            throw new InvalidMemoryLimitException(sprintf('Unable to parse memory limit "%s".', $value));
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2] ?? '');

        $multiplier = match ($unit) {
            'k', 'kb' => 1024,
            'm', 'mb' => 1024 ** 2,
            'g', 'gb' => 1024 ** 3,
            't', 'tb' => 1024 ** 4,
            '', 'b' => 1,
            default => throw new InvalidMemoryLimitException(sprintf('Unknown memory unit in "%s".', $value)),
        };

        return (int) round($number * $multiplier);
    }
}
