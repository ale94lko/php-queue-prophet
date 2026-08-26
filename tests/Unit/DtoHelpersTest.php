<?php

declare(strict_types=1);

namespace PhpQueueProphet\Tests\Unit;

use PhpQueueProphet\DTO\HealthReport;
use PhpQueueProphet\DTO\QueueMetrics;
use PhpQueueProphet\Tests\TestCase;

final class DtoHelpersTest extends TestCase
{
    public function testHealthReportUsagePercentWithZeroLimit(): void
    {
        $report = new HealthReport(
            currentMemoryBytes: 100,
            memoryLimitBytes: 0,
            leakRatePerJobBytes: 0.0,
            estimatedRemainingJobs: null,
            sampleCount: 0,
        );

        $this->assertSame(0.0, $report->getMemoryUsagePercent());
    }

    public function testHealthReportIsAtRiskWhenRemainingIsNull(): void
    {
        $report = new HealthReport(
            currentMemoryBytes: 100,
            memoryLimitBytes: 1000,
            leakRatePerJobBytes: 0.0,
            estimatedRemainingJobs: null,
            sampleCount: 1,
        );

        $this->assertFalse($report->isAtRisk());
    }

    public function testHealthReportIsAtRiskWhenRemainingIsInfinite(): void
    {
        $report = new HealthReport(
            currentMemoryBytes: 100,
            memoryLimitBytes: 1000,
            leakRatePerJobBytes: 0.0,
            estimatedRemainingJobs: INF,
            sampleCount: 5,
        );

        $this->assertFalse($report->isAtRisk());
    }

    public function testQueueMetricsFillPercentWithZeroCapacity(): void
    {
        $metrics = new QueueMetrics(
            currentDepth: 10,
            maxCapacity: 0,
            arrivalRate: 1.0,
            processingRate: 1.0,
            estimatedSecondsToOverflow: null,
        );

        $this->assertSame(0.0, $metrics->getFillPercent());
    }

    public function testQueueMetricsIsAtRiskWhenEstimateIsNull(): void
    {
        $metrics = new QueueMetrics(
            currentDepth: 10,
            maxCapacity: 100,
            arrivalRate: 1.0,
            processingRate: 1.0,
            estimatedSecondsToOverflow: null,
        );

        $this->assertFalse($metrics->isAtRisk());
    }
}
