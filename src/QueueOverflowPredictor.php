<?php

declare(strict_types=1);

namespace PhpQueueProphet;

use PhpQueueProphet\Contracts\QueueTrackerInterface;
use PhpQueueProphet\DTO\QueueMetrics;
use PhpQueueProphet\Exceptions\InsufficientDataException;
use PhpQueueProphet\Exceptions\InvalidQueueCapacityException;

final class QueueOverflowPredictor implements QueueTrackerInterface
{
    private ?int $currentDepth = null;
    private float $arrivalRate = 0.0;
    private float $processingRate = 0.0;

    public function __construct(
        private readonly int $maxCapacity,
    ) {
        if ($this->maxCapacity <= 0) {
            throw new InvalidQueueCapacityException('Max queue capacity must be greater than zero.');
        }
    }

    public function record(int $currentDepth, float $arrivalRate, float $processingRate): void
    {
        if ($currentDepth < 0) {
            throw new InvalidQueueCapacityException('Queue depth must be a non-negative integer.');
        }

        if ($arrivalRate < 0.0 || $processingRate < 0.0) {
            throw new InvalidQueueCapacityException('Arrival and processing rates must be non-negative.');
        }

        $this->currentDepth = $currentDepth;
        $this->arrivalRate = $arrivalRate;
        $this->processingRate = $processingRate;
    }

    public function predictTimeToOverflow(): ?float
    {
        if ($this->currentDepth === null) {
            return null;
        }

        if ($this->currentDepth >= $this->maxCapacity) {
            return 0.0;
        }

        $netRate = $this->arrivalRate - $this->processingRate;

        if ($netRate <= 0.0) {
            return INF;
        }

        $remainingCapacity = $this->maxCapacity - $this->currentDepth;

        return $remainingCapacity / $netRate;
    }

    /**
     * Like predictTimeToOverflow(), but throws when no sample has been recorded.
     *
     * @throws InsufficientDataException
     */
    public function predictTimeToOverflowOrFail(): float
    {
        $result = $this->predictTimeToOverflow();

        if ($result === null) {
            throw new InsufficientDataException('At least one queue sample is required to predict time-to-overflow.');
        }

        return $result;
    }

    public function getMetrics(): QueueMetrics
    {
        return new QueueMetrics(
            currentDepth: $this->currentDepth ?? 0,
            maxCapacity: $this->maxCapacity,
            arrivalRate: $this->arrivalRate,
            processingRate: $this->processingRate,
            estimatedSecondsToOverflow: $this->predictTimeToOverflow(),
        );
    }

    public function getMaxCapacity(): int
    {
        return $this->maxCapacity;
    }

    /**
     * Clears the last recorded sample.
     */
    public function reset(): void
    {
        $this->currentDepth = null;
        $this->arrivalRate = 0.0;
        $this->processingRate = 0.0;
    }
}
