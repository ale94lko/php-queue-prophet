<?php

declare(strict_types=1);

namespace Playground;

use PhpQueueProphet\WorkerHealthPredictor;

final class StableMemoryDemo
{
    /**
     * Flat memory → INF remaining (no OOM risk from leakage).
     */
    public function run(): void
    {
        Console::header('Demo: Stable memory (no leak)');

        $predictor = new WorkerHealthPredictor(
            memoryLimit: '16M',
            sampleWindowSize: 10,
            triggerGarbageCollection: false,
        );

        $baseline = 4 * 1024 * 1024;

        for ($i = 1; $i <= 8; $i++) {
            $predictor->recordSample($baseline);

            $remaining = $predictor->predictRemainingJobs();
            $label = $remaining === null
                ? 'n/a'
                : (is_infinite($remaining) ? 'INF (safe)' : (string) $remaining);

            Console::line(sprintf('  Job %-2d  remaining jobs: %s', $i, $label));
        }

        $report = $predictor->generateHealthReport();
        Console::line();
        Console::kv('Leak rate', number_format($report->leakRatePerJobBytes, 2) . ' bytes/job');
        Console::kv('Has leak?', $report->hasLeak() ? 'yes' : 'no');
        Console::line();
    }
}
