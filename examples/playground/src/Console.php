<?php

declare(strict_types=1);

namespace Playground;

final class Console
{
    public static function line(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public static function header(string $title): void
    {
        self::line();
        self::line(str_repeat('=', 60));
        self::line('  ' . $title);
        self::line(str_repeat('=', 60));
        self::line();
    }

    public static function kv(string $key, string|int|float $value): void
    {
        self::line(sprintf('  %-28s %s', $key . ':', (string) $value));
    }
}
