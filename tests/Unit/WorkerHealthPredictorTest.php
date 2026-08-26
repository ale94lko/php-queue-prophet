<?php

declare(strict_types=1);

namespace PhpQueueProphet\Tests\Unit;

use InvalidArgumentException;
use PhpQueueProphet\Exceptions\InsufficientDataException;
use PhpQueueProphet\Exceptions\InvalidMemoryLimitException;
use PhpQueueProphet\Tests\TestCase;
use PhpQueueProphet\WorkerHealthPredictor;

final class WorkerHealthPredictorTest extends TestCase
{
    public function testPredictReturnsNullWithInsufficientSamples(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 1024 * 1024, sampleWindowSize: 5, triggerGarbageCollection: false);

        $this->assertNull($predictor->predictRemainingJobs());

        $predictor->recordSample(1000);
        $this->assertNull($predictor->predictRemainingJobs());

        $predictor->recordSample(1100);
        $this->assertNull($predictor->predictRemainingJobs());

        $predictor->recordSample(1200);
        $this->assertNotNull($predictor->predictRemainingJobs());
    }

    public function testPredictReturnsInfWhenMemoryIsFlat(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 10_000_000, sampleWindowSize: 5, triggerGarbageCollection: false);

        foreach ([5000, 5000, 5000, 5000] as $sample) {
            $predictor->recordSample($sample);
        }

        $this->assertInfinite($predictor->predictRemainingJobs());
        $this->assertFalse($predictor->generateHealthReport()->hasLeak());
    }

    public function testPredictReturnsInfWhenMemoryDecreases(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 10_000_000, sampleWindowSize: 5, triggerGarbageCollection: false);

        foreach ([8000, 7000, 6000, 5000] as $sample) {
            $predictor->recordSample($sample);
        }

        $remaining = $predictor->predictRemainingJobs();
        $this->assertNotNull($remaining);
        $this->assertInfinite($remaining);
    }

    public function testPredictsRemainingJobsForLinearLeak(): void
    {
        // Leak of 1000 bytes/job, limit 10_000, current after 5 samples: 6000
        // Remaining bytes = 4000 → remaining jobs = 4
        $predictor = new WorkerHealthPredictor(memoryLimit: 10_000, sampleWindowSize: 10, triggerGarbageCollection: false);

        foreach ([2000, 3000, 4000, 5000, 6000] as $sample) {
            $predictor->recordSample($sample);
        }

        $remaining = $predictor->predictRemainingJobs();
        $this->assertNotNull($remaining);
        $this->assertEqualsWithDelta(4.0, $remaining, 1e-9);

        $report = $predictor->generateHealthReport();
        $this->assertSame(6000, $report->currentMemoryBytes);
        $this->assertSame(10_000, $report->memoryLimitBytes);
        $this->assertEqualsWithDelta(1000.0, $report->leakRatePerJobBytes, 1e-9);
        $this->assertSame(5, $report->sampleCount);
        $this->assertEqualsWithDelta(1.0, $report->rSquared, 1e-9);
        $this->assertTrue($report->hasLeak());
        $this->assertTrue($report->isAtRisk(50));
        $this->assertFalse($report->isAtRisk(3));
    }

    public function testReturnsZeroWhenAlreadyOverLimit(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 5000, sampleWindowSize: 5, triggerGarbageCollection: false);

        foreach ([3000, 4000, 5000, 6000] as $sample) {
            $predictor->recordSample($sample);
        }

        $this->assertSame(0.0, $predictor->predictRemainingJobs());
    }

    public function testSlidingWindowIsBounded(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: '1M', sampleWindowSize: 3, triggerGarbageCollection: false);

        $predictor->recordSample(100);
        $predictor->recordSample(200);
        $predictor->recordSample(300);
        $predictor->recordSample(400);

        $this->assertSame(3, $predictor->getSampleCount());
    }

    public function testParseMemoryLimitStrings(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: '128M', sampleWindowSize: 5, triggerGarbageCollection: false);
        $this->assertSame(128 * 1024 * 1024, $predictor->getMemoryLimitBytes());

        $predictorKb = new WorkerHealthPredictor(memoryLimit: '512K', sampleWindowSize: 5, triggerGarbageCollection: false);
        $this->assertSame(512 * 1024, $predictorKb->getMemoryLimitBytes());

        $predictorGb = new WorkerHealthPredictor(memoryLimit: '1G', sampleWindowSize: 5, triggerGarbageCollection: false);
        $this->assertSame(1024 ** 3, $predictorGb->getMemoryLimitBytes());

        $predictorMb = new WorkerHealthPredictor(memoryLimit: '64MB', sampleWindowSize: 5, triggerGarbageCollection: false);
        $this->assertSame(64 * 1024 * 1024, $predictorMb->getMemoryLimitBytes());
    }

    public function testInvalidMemoryLimitThrows(): void
    {
        $this->expectException(InvalidMemoryLimitException::class);
        new WorkerHealthPredictor(memoryLimit: 0);
    }

    public function testUnparseableMemoryLimitThrows(): void
    {
        $this->expectException(InvalidMemoryLimitException::class);
        new WorkerHealthPredictor(memoryLimit: 'lots');
    }

    public function testNegativeMemoryLimitStringThrows(): void
    {
        $this->expectException(InvalidMemoryLimitException::class);
        new WorkerHealthPredictor(memoryLimit: '-10M');
    }

    public function testSmallWindowSizeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WorkerHealthPredictor(memoryLimit: 1024, sampleWindowSize: 2);
    }

    public function testNegativeSampleThrows(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 1024, sampleWindowSize: 5, triggerGarbageCollection: false);

        $this->expectException(InvalidArgumentException::class);
        $predictor->recordSample(-1);
    }

    public function testPredictOrFailThrowsOnInsufficientData(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 1024, sampleWindowSize: 5, triggerGarbageCollection: false);

        $this->expectException(InsufficientDataException::class);
        $predictor->predictRemainingJobsOrFail();
    }

    public function testResetClearsSamples(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 10_000, sampleWindowSize: 5, triggerGarbageCollection: false);
        $predictor->recordSample(1000);
        $predictor->recordSample(2000);
        $predictor->reset();

        $this->assertSame(0, $predictor->getSampleCount());
        $this->assertNull($predictor->predictRemainingJobs());
    }

    public function testHealthReportMemoryHelpers(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: 2 * 1024 * 1024, sampleWindowSize: 5, triggerGarbageCollection: false);
        $predictor->recordSample(1024 * 1024);
        $predictor->recordSample(1024 * 1024);
        $predictor->recordSample(1024 * 1024);

        $report = $predictor->generateHealthReport();
        $this->assertSame(1.0, $report->getCurrentMemoryMB());
        $this->assertSame(2.0, $report->getMemoryLimitMB());
        $this->assertSame(50.0, $report->getMemoryUsagePercent());
    }

    public function testGenerateHealthReportWithoutSamplesUsesLiveMemory(): void
    {
        $predictor = new WorkerHealthPredictor(memoryLimit: '128M', sampleWindowSize: 5, triggerGarbageCollection: false);
        $report = $predictor->generateHealthReport();

        $this->assertGreaterThan(0, $report->currentMemoryBytes);
        $this->assertSame(0, $report->sampleCount);
        $this->assertNull($report->estimatedRemainingJobs);
    }
}
