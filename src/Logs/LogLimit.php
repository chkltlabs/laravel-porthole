<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Logs;

use Illuminate\Support\Facades\Config;

final class LogLimit
{
    public static function lines(mixed $lines): int
    {
        $default = max(1, (int) Config::get('porthole.logs.lines', 200));
        $max = max($default, (int) Config::get('porthole.logs.lines_max', 2000));
        $value = is_numeric($lines) ? (int) $lines : $default;

        return max(1, min($value, $max));
    }

    public static function bytes(): int
    {
        return max(1024, (int) Config::get('porthole.logs.bytes_max', 262144));
    }

    public static function searchMatches(mixed $limit): int
    {
        $default = max(1, (int) Config::get('porthole.logs.search_matches', 50));
        $max = max($default, (int) Config::get('porthole.logs.search_matches_max', 200));
        $value = is_numeric($limit) ? (int) $limit : $default;

        return max(1, min($value, $max));
    }
}
