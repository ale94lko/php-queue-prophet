<?php

declare(strict_types=1);

namespace PhpQueueProphet\DTO;

final class HealthReport
{
    public function __construct(
        public readonly int $currentMemoryBytes,
        public readonly int $memoryLimitBytes,
        public readonly float $leakRatePerJobBytes,
        public readonly ?float $estimatedRemainingJobs,
        public readonly int $sampleCount,
        public readonly float $rSquared = 0.0,
    ) {}

    public function getCurrentMemoryMB(): float
    {
        return round($this->currentMemoryBytes / (1024 * 1024), 2);
    }

    public function getMemoryLimitMB(): float
    {
        return round($this->memoryLimitBytes / (1024 * 1024), 2);
    }

    /**
     * Memory usage as a percentage of the configured limit (0–100).
     */
    public function getMemoryUsagePercent(): float
    {
        if ($this->memoryLimitBytes <= 0) {
            return 0.0;
        }

        return round(($this->currentMemoryBytes / $this->memoryLimitBytes) * 100, 2);
    }

    /**
     * True when a positive leak is detected and remaining jobs are below the threshold.
     */
    public function isAtRisk(float $remainingJobsThreshold = 50.0): bool
    {
        if ($this->estimatedRemainingJobs === null) {
            return false;
        }

        if (is_infinite($this->estimatedRemainingJobs)) {
            return false;
        }

        return $this->estimatedRemainingJobs < $remainingJobsThreshold;
    }

    public function hasLeak(): bool
    {
        return $this->leakRatePerJobBytes > 0.0;
    }
}
