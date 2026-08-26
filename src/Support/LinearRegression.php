<?php

declare(strict_types=1);

namespace PhpQueueProphet\Support;

/**
 * Ordinary least-squares linear regression over a sequence of Y values
 * against zero-based X indices (0..N-1).
 *
 * Used as the mathematical engine for memory-leak slope estimation.
 */
final class LinearRegression
{
    /**
     * @param list<float|int> $yValues Dependent variable samples (e.g. memory bytes).
     */
    public function __construct(
        private readonly array $yValues,
    ) {}

    public function count(): int
    {
        return count($this->yValues);
    }

    /**
     * Slope m of the best-fit line y = mx + b.
     *
     * Returns 0.0 when fewer than 2 points are available or when the
     * denominator is zero (all X identical — impossible with 0..N-1).
     */
    public function slope(): float
    {
        $n = $this->count();
        if ($n < 2) {
            return 0.0;
        }

        $sums = $this->sums();
        $denominator = ($n * $sums['x2'] - $sums['x'] * $sums['x']);

        if ($denominator == 0.0) {
            return 0.0;
        }

        return ($n * $sums['xy'] - $sums['x'] * $sums['y']) / $denominator;
    }

    /**
     * Y-intercept b of the best-fit line y = mx + b.
     */
    public function intercept(): float
    {
        $n = $this->count();
        if ($n < 1) {
            return 0.0;
        }

        $sums = $this->sums();
        $slope = $this->slope();

        return ($sums['y'] - $slope * $sums['x']) / $n;
    }

    /**
     * Coefficient of determination R² in [0, 1].
     *
     * Returns 0.0 when variance of Y is zero or when fewer than 2 points exist.
     */
    public function rSquared(): float
    {
        $n = $this->count();
        if ($n < 2) {
            return 0.0;
        }

        $slope = $this->slope();
        $intercept = $this->intercept();
        $yMean = array_sum($this->yValues) / $n;

        $ssTot = 0.0;
        $ssRes = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $y = (float) $this->yValues[$i];
            $predicted = $slope * $i + $intercept;
            $ssTot += ($y - $yMean) ** 2;
            $ssRes += ($y - $predicted) ** 2;
        }

        if ($ssTot == 0.0) {
            return 0.0;
        }

        $r2 = 1.0 - ($ssRes / $ssTot);

        // Numerical noise can push R² slightly outside [0, 1].
        return max(0.0, min(1.0, $r2));
    }

    /**
     * @return array{x: float, y: float, xy: float, x2: float}
     */
    private function sums(): array
    {
        $xSum = 0.0;
        $ySum = 0.0;
        $xySum = 0.0;
        $x2Sum = 0.0;
        $n = $this->count();

        for ($i = 0; $i < $n; $i++) {
            $y = (float) $this->yValues[$i];
            $xSum += $i;
            $ySum += $y;
            $xySum += $i * $y;
            $x2Sum += $i * $i;
        }

        return [
            'x' => $xSum,
            'y' => $ySum,
            'xy' => $xySum,
            'x2' => $x2Sum,
        ];
    }
}
