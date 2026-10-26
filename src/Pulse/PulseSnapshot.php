<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Pulse;

use Carbon\CarbonInterval;
use Chkltlabs\ObservabilityMcp\Support\Redactor;
use Chkltlabs\ObservabilityMcp\Support\ResultLimit;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;

final class PulseSnapshot
{
    public function __construct(private Redactor $redactor) {}

    /**
     * @param  list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @return list<array<string, mixed>>
     */
    public function rows(string $type, string $period, array $aggregates, int $limit, ?string $search = null): array
    {
        $storage = app(Storage::class);
        $rows = $storage->aggregate(
            $type,
            $aggregates,
            $this->interval($period),
            $aggregates[0] ?? 'count',
            'desc',
            ResultLimit::cap($limit),
        );

        return $this->filter($this->mapRows($rows, $type), $search);
    }

    public function total(string $type, string $period): int
    {
        $total = app(Storage::class)->aggregateTotal(
            $type,
            'count',
            $this->interval($period),
        );

        if ($total instanceof Collection) {
            return (int) $total->sum();
        }

        return (int) $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function servers(): array
    {
        $values = app(Storage::class)->values('system');

        return collect($values)->map(function (object $row): array {
            $decoded = json_decode((string) ($row->value ?? ''), true);

            return [
                'server' => $row->key ?? null,
                'timestamp' => $row->timestamp ?? null,
                'system' => is_array($decoded) ? $this->redactor->redact($decoded) : null,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function queues(string $period, int $limit): array
    {
        $queues = [];

        foreach (['queued', 'processing', 'processed', 'released', 'failed'] as $type) {
            foreach ($this->rows($type, $period, ['count'], $limit) as $row) {
                $name = (string) ($row['key'] ?? '');
                $queues[$name] ??= ['queue' => $name];
                $queues[$name][$type] = $row['count'] ?? 0;
            }
        }

        return ['queues' => array_values($queues)];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRows(Collection $rows, string $type): array
    {
        return $rows->map(function (object $row) use ($type): array {
            $key = (string) ($row->key ?? '');

            if ($type === 'slow_query' || $type === 'slow_outgoing_request') {
                $key = $this->redactor->redactSql($key);
            } else {
                $key = $this->redactor->truncate($key);
            }

            return [
                'key' => $key,
                'count' => $row->count ?? null,
                'avg' => $row->avg ?? null,
                'max' => $row->max ?? null,
                'min' => $row->min ?? null,
                'sum' => $row->sum ?? null,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filter(array $rows, ?string $search): array
    {
        if ($search === null || $search === '') {
            return $rows;
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => str_contains(mb_strtolower((string) ($row['key'] ?? '')), $needle),
        ));
    }

    private function interval(string $period): CarbonInterval
    {
        return match ($period) {
            '1h' => CarbonInterval::hour(),
            '6h' => CarbonInterval::hours(6),
            '7d' => CarbonInterval::week(),
            default => CarbonInterval::day(),
        };
    }
}
