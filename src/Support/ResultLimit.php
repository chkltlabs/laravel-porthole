<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Support;

use Illuminate\Support\Facades\Config;

final class ResultLimit
{
    public static function cap(mixed $limit): int
    {
        $default = max(1, (int) Config::get('observability-mcp.limits.default', 25));
        $max = max($default, (int) Config::get('observability-mcp.limits.max', 100));
        $value = is_numeric($limit) ? (int) $limit : $default;

        return max(1, min($value, $max));
    }
}
