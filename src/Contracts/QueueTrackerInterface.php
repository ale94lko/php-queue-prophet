<?php

declare(strict_types=1);

namespace PhpQueueProphet\Contracts;

use PhpQueueProphet\DTO\QueueMetrics;

interface QueueTrackerInterface
{
    /**
     * Records the current queue depth together with arrival and processing rates.
     *
     * Rates are expressed in items per second.
     */
    public function record(int $currentDepth, float $arrivalRate, float $processingRate): void;

    /**
     * Estimates seconds until the queue reaches max capacity.
     *
     * Returns null when no sample has been recorded yet.
     * Returns INF when the queue is draining or stable (net rate <= 0).
     */
    public function predictTimeToOverflow(): ?float;

    /**
     * Returns the latest immutable queue metrics snapshot.
     */
    public function getMetrics(): QueueMetrics;
}
