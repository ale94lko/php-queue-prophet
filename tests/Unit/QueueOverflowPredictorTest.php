<?php

declare(strict_types=1);

namespace PhpQueueProphet\Tests\Unit;

use PhpQueueProphet\Exceptions\InsufficientDataException;
use PhpQueueProphet\Exceptions\InvalidQueueCapacityException;
use PhpQueueProphet\QueueOverflowPredictor;
use PhpQueueProphet\Tests\TestCase;

final class QueueOverflowPredictorTest extends TestCase
{
    public function testPredictReturnsNullWithoutSamples(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 1000);

        $this->assertNull($predictor->predictTimeToOverflow());
        $this->assertNull($predictor->getMetrics()->estimatedSecondsToOverflow);
    }

    public function testPredictsTimeToOverflow(): void
    {
        // Capacity 1000, depth 400, net rate 20/s → (600 / 20) = 30s
        $predictor = new QueueOverflowPredictor(maxCapacity: 1000);
        $predictor->record(currentDepth: 400, arrivalRate: 50.0, processingRate: 30.0);

        $tto = $predictor->predictTimeToOverflow();
        $this->assertNotNull($tto);
        $this->assertEqualsWithDelta(30.0, $tto, 1e-9);

        $metrics = $predictor->getMetrics();
        $this->assertSame(400, $metrics->currentDepth);
        $this->assertSame(1000, $metrics->maxCapacity);
        $this->assertEqualsWithDelta(20.0, $metrics->getNetRate(), 1e-9);
        $this->assertSame(40.0, $metrics->getFillPercent());
        $this->assertTrue($metrics->isGrowing());
        $this->assertTrue($metrics->isAtRisk(60));
        $this->assertFalse($metrics->isAtRisk(10));
    }

    public function testReturnsInfWhenQueueIsDraining(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 1000);
        $predictor->record(currentDepth: 500, arrivalRate: 10.0, processingRate: 25.0);

        $this->assertInfinite($predictor->predictTimeToOverflow());
        $this->assertFalse($predictor->getMetrics()->isGrowing());
        $this->assertFalse($predictor->getMetrics()->isAtRisk());
    }

    public function testReturnsInfWhenRatesAreEqual(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 1000);
        $predictor->record(currentDepth: 500, arrivalRate: 20.0, processingRate: 20.0);

        $this->assertInfinite($predictor->predictTimeToOverflow());
    }

    public function testReturnsZeroWhenAlreadyAtCapacity(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 100);
        $predictor->record(currentDepth: 100, arrivalRate: 10.0, processingRate: 1.0);

        $this->assertSame(0.0, $predictor->predictTimeToOverflow());
    }

    public function testReturnsZeroWhenOverCapacity(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 100);
        $predictor->record(currentDepth: 150, arrivalRate: 10.0, processingRate: 1.0);

        $this->assertSame(0.0, $predictor->predictTimeToOverflow());
    }

    public function testInvalidCapacityThrows(): void
    {
        $this->expectException(InvalidQueueCapacityException::class);
        new QueueOverflowPredictor(maxCapacity: 0);
    }

    public function testNegativeDepthThrows(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 100);

        $this->expectException(InvalidQueueCapacityException::class);
        $predictor->record(currentDepth: -1, arrivalRate: 1.0, processingRate: 1.0);
    }

    public function testNegativeRateThrows(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 100);

        $this->expectException(InvalidQueueCapacityException::class);
        $predictor->record(currentDepth: 10, arrivalRate: -1.0, processingRate: 1.0);
    }

    public function testPredictOrFailThrowsWithoutData(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 100);

        $this->expectException(InsufficientDataException::class);
        $predictor->predictTimeToOverflowOrFail();
    }

    public function testResetClearsState(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 100);
        $predictor->record(currentDepth: 50, arrivalRate: 10.0, processingRate: 1.0);
        $predictor->reset();

        $this->assertNull($predictor->predictTimeToOverflow());
        $this->assertSame(0, $predictor->getMetrics()->currentDepth);
    }

    public function testGetMaxCapacity(): void
    {
        $predictor = new QueueOverflowPredictor(maxCapacity: 2500);
        $this->assertSame(2500, $predictor->getMaxCapacity());
    }
}
