<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Support;

use Illuminate\Support\Facades\Config;

final class ResultLimit
{
    public static function cap(mixed $limit): int
    {
        $default = max(1, (int) Config::get('porthole.limits.default', 25));
        $max = max($default, (int) Config::get('porthole.limits.max', 100));
        $value = is_numeric($limit) ? (int) $limit : $default;

        return max(1, min($value, $max));
    }
}
