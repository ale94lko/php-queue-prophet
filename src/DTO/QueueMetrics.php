<?php

declare(strict_types=1);

namespace PhpQueueProphet\DTO;

final class QueueMetrics
{
    public function __construct(
        public readonly int $currentDepth,
        public readonly int $maxCapacity,
        public readonly float $arrivalRate,
        public readonly float $processingRate,
        public readonly ?float $estimatedSecondsToOverflow,
    ) {}

    /**
     * Net growth rate in items per second. Positive means the queue is growing.
     */
    public function getNetRate(): float
    {
        return $this->arrivalRate - $this->processingRate;
    }

    /**
     * Queue fill level as a percentage of max capacity (0–100).
     */
    public function getFillPercent(): float
    {
        if ($this->maxCapacity <= 0) {
            return 0.0;
        }

        return round(($this->currentDepth / $this->maxCapacity) * 100, 2);
    }

    /**
     * True when the queue is growing and overflow is expected within the threshold.
     */
    public function isAtRisk(float $secondsThreshold = 300.0): bool
    {
        if ($this->estimatedSecondsToOverflow === null) {
            return false;
        }

        if (is_infinite($this->estimatedSecondsToOverflow)) {
            return false;
        }

        return $this->estimatedSecondsToOverflow < $secondsThreshold;
    }

    public function isGrowing(): bool
    {
        return $this->getNetRate() > 0.0;
    }
}
