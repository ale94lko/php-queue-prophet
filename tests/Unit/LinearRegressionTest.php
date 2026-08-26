<?php

declare(strict_types=1);

namespace PhpQueueProphet\Tests\Unit;

use PhpQueueProphet\Support\LinearRegression;
use PhpQueueProphet\Tests\TestCase;

final class LinearRegressionTest extends TestCase
{
    public function testSlopeOfPerfectLinearIncrease(): void
    {
        // y = 10x + 100 → samples [100, 110, 120, 130, 140]
        $regression = new LinearRegression([100, 110, 120, 130, 140]);

        $this->assertEqualsWithDelta(10.0, $regression->slope(), 1e-9);
        $this->assertEqualsWithDelta(100.0, $regression->intercept(), 1e-9);
        $this->assertEqualsWithDelta(1.0, $regression->rSquared(), 1e-9);
    }

    public function testSlopeIsZeroWhenValuesAreConstant(): void
    {
        $regression = new LinearRegression([50, 50, 50, 50]);

        $this->assertEqualsWithDelta(0.0, $regression->slope(), 1e-9);
        $this->assertEqualsWithDelta(0.0, $regression->rSquared(), 1e-9);
    }

    public function testNegativeSlopeWhenValuesDecrease(): void
    {
        // y = -5x + 100 → [100, 95, 90, 85]
        $regression = new LinearRegression([100, 95, 90, 85]);

        $this->assertEqualsWithDelta(-5.0, $regression->slope(), 1e-9);
        $this->assertEqualsWithDelta(1.0, $regression->rSquared(), 1e-9);
    }

    public function testInsufficientPointsReturnZeroSlope(): void
    {
        $empty = new LinearRegression([]);
        $this->assertSame(0, $empty->count());
        $this->assertSame(0.0, $empty->slope());
        $this->assertSame(0.0, $empty->intercept());
        $this->assertSame(0.0, $empty->rSquared());

        $single = new LinearRegression([42]);
        $this->assertSame(0.0, $single->slope());
        $this->assertEqualsWithDelta(42.0, $single->intercept(), 1e-9);
    }

    public function testTwoPointsSlope(): void
    {
        $regression = new LinearRegression([10, 20]);

        $this->assertEqualsWithDelta(10.0, $regression->slope(), 1e-9);
        $this->assertEqualsWithDelta(10.0, $regression->intercept(), 1e-9);
        $this->assertEqualsWithDelta(1.0, $regression->rSquared(), 1e-9);
    }

    public function testNoisyDataHasRSquaredBetweenZeroAndOne(): void
    {
        $regression = new LinearRegression([10, 22, 28, 41, 49]);
        $r2 = $regression->rSquared();

        $this->assertGreaterThan(0.0, $r2);
        $this->assertLessThanOrEqual(1.0, $r2);
        $this->assertGreaterThan(0.0, $regression->slope());
    }
}
