<?php

declare(strict_types=1);

namespace PhpQueueProphet\Contracts;

use PhpQueueProphet\DTO\HealthReport;

interface PredictorInterface
{
    /**
     * Records a memory consumption sample into the sliding window.
     *
     * When $memoryBytes is null, the current process memory usage is used.
     */
    public function recordSample(?int $memoryBytes = null): void;

    /**
     * Estimates remaining job iterations before the configured memory limit.
     *
     * Returns null when fewer than the minimum required samples are available.
     * Returns INF when no memory leak is detected (slope <= 0).
     */
    public function predictRemainingJobs(): ?float;

    /**
     * Generates a structured immutable health report.
     */
    public function generateHealthReport(): HealthReport;
}
