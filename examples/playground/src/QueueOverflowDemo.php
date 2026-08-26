<?php

declare(strict_types=1);

namespace Playground;

use PhpQueueProphet\QueueOverflowPredictor;

final class QueueOverflowDemo
{
    /**
     * Simulates a queue filling faster than workers can drain it.
     */
    public function run(): void
    {
        Console::header('Demo: Queue Time-to-Overflow (TTO)');

        $tracker = new QueueOverflowPredictor(maxCapacity: 10_000);

        $scenarios = [
            ['label' => 'Healthy (draining)', 'depth' => 3_000, 'in' => 20.0, 'out' => 35.0],
            ['label' => 'Balanced', 'depth' => 5_000, 'in' => 40.0, 'out' => 40.0],
            ['label' => 'Growing slowly', 'depth' => 6_000, 'in' => 55.0, 'out' => 50.0],
            ['label' => 'Spike / at risk', 'depth' => 8_500, 'in' => 120.0, 'out' => 40.0],
            ['label' => 'Already full', 'depth' => 10_000, 'in' => 80.0, 'out' => 20.0],
        ];

        Console::line(sprintf(
            '  %-20s %-10s %-10s %-10s %-12s %s',
            'Scenario',
            'Depth',
            'In/s',
            'Out/s',
            'TTO (s)',
            'Risk?',
        ));
        Console::line('  ' . str_repeat('-', 72));

        foreach ($scenarios as $scenario) {
            $tracker->record(
                currentDepth: $scenario['depth'],
                arrivalRate: $scenario['in'],
                processingRate: $scenario['out'],
            );

            $metrics = $tracker->getMetrics();
            $tto = $metrics->estimatedSecondsToOverflow;
            $ttoLabel = match (true) {
                $tto === null => 'n/a',
                is_infinite($tto) => 'INF',
                default => number_format($tto, 1),
            };

            Console::line(sprintf(
                '  %-20s %-10d %-10.1f %-10.1f %-12s %s',
                $scenario['label'],
                $scenario['depth'],
                $scenario['in'],
                $scenario['out'],
                $ttoLabel,
                $metrics->isAtRisk(300) ? 'YES' : 'no',
            ));
        }

        Console::line();
        Console::line('  Tip: alert / autoscale when isAtRisk(seconds) is true.');
        Console::line();
    }
}
