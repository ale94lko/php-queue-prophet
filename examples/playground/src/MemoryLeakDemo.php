<?php

declare(strict_types=1);

namespace Playground;

use PhpQueueProphet\WorkerHealthPredictor;

final class MemoryLeakDemo
{
    /**
     * Simulates a worker that leaks ~256 KB per job until the predictor
     * recommends a graceful stop.
     */
    public function run(int $leakPerJobBytes = 262_144, int $stopBelowJobs = 8): void
    {
        Console::header('Demo: Worker memory leak → OOM forecast');

        $memoryLimitBytes = 8 * 1024 * 1024; // 8 MB virtual budget
        $startBytes = 2 * 1024 * 1024;       // start at 2 MB

        $predictor = new WorkerHealthPredictor(
            memoryLimit: $memoryLimitBytes,
            sampleWindowSize: 15,
            triggerGarbageCollection: false,
        );

        Console::kv('Memory limit', $this->formatBytes($memoryLimitBytes));
        Console::kv('Simulated leak / job', $this->formatBytes($leakPerJobBytes));
        Console::kv('Stop threshold', $stopBelowJobs . ' remaining jobs');
        Console::line();
        Console::line(sprintf(
            '  %-6s %-14s %-14s %-14s %s',
            'Job',
            'Memory',
            'Leak rate',
            'Remaining',
            'Status',
        ));
        Console::line('  ' . str_repeat('-', 62));

        $current = $startBytes;
        $job = 0;

        while (true) {
            $job++;
            $current += $leakPerJobBytes;
            $predictor->recordSample($current);

            $report = $predictor->generateHealthReport();
            $remaining = $report->estimatedRemainingJobs;

            $remainingLabel = match (true) {
                $remaining === null => 'n/a',
                is_infinite($remaining) => 'INF',
                default => number_format($remaining, 1),
            };

            $status = 'ok';
            if ($remaining !== null && !is_infinite($remaining) && $remaining < $stopBelowJobs) {
                $status = 'STOP';
            }

            Console::line(sprintf(
                '  %-6d %-14s %-14s %-14s %s',
                $job,
                $this->formatBytes($report->currentMemoryBytes),
                $this->formatBytes((int) round($report->leakRatePerJobBytes)),
                $remainingLabel,
                $status,
            ));

            if ($status === 'STOP') {
                Console::line();
                Console::line('  → Predictor recommends recycling the worker before OOM.');
                Console::kv('R² fit', number_format($report->rSquared, 4));
                Console::kv('Usage', $report->getMemoryUsagePercent() . '%');
                break;
            }

            if ($job >= 100) {
                Console::line();
                Console::line('  Safety stop after 100 jobs.');
                break;
            }
        }

        Console::line();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 ** 2) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / (1024 ** 2), 2) . ' MB';
    }
}
