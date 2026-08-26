#!/usr/bin/env php
<?php

declare(strict_types=1);

use Playground\Console;
use Playground\MemoryLeakDemo;
use Playground\QueueOverflowDemo;
use Playground\StableMemoryDemo;

require dirname(__DIR__) . '/vendor/autoload.php';

$command = $argv[1] ?? 'all';

$help = static function (): void {
    Console::line('php-queue-prophet playground');
    Console::line();
    Console::line('Usage:');
    Console::line('  php bin/demo.php [all|memory|stable|queue|help]');
    Console::line();
    Console::line('Or via Composer:');
    Console::line('  composer demo');
    Console::line('  composer demo:memory');
    Console::line('  composer demo:stable');
    Console::line('  composer demo:queue');
    Console::line();
};

match ($command) {
    'help', '-h', '--help' => $help(),
    'memory' => (new MemoryLeakDemo())->run(),
    'stable' => (new StableMemoryDemo())->run(),
    'queue' => (new QueueOverflowDemo())->run(),
    'all' => (static function (): void {
        (new MemoryLeakDemo())->run();
        (new StableMemoryDemo())->run();
        (new QueueOverflowDemo())->run();
        Console::line('Done. Tweak values in src/*.php and re-run.');
        Console::line();
    })(),
    default => (static function () use ($command, $help): void {
        Console::line("Unknown command: {$command}");
        $help();
        exit(1);
    })(),
};
