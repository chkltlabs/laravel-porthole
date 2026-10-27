<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Telescope;

use Chkltlabs\Porthole\Support\PackageRoutes;
use Chkltlabs\Porthole\Support\Redactor;
use Chkltlabs\Porthole\Support\ResultLimit;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Laravel\Telescope\Telescope;

final class TelescopeSnapshot
{
    public function __construct(
        private Redactor $redactor,
        private PackageRoutes $routes,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $type, ?string $tag, ?string $batch, ?string $family, int $limit): array
    {
        return $this->withoutRecording(function () use ($type, $tag, $batch, $family, $limit): array {
            $options = EntryQueryOptions::forBatchId($batch)
                ->tag($tag)
                ->familyHash($family)
                ->limit(ResultLimit::cap($limit));

            return collect($this->repository()->get($type, $options))
                ->map(fn (EntryResult $entry): array => $this->present($entry, false))
                ->values()
                ->all();
        });
    }

    /**
     * @param  list<string>  $types
     * @return array<string, mixed>|null
     */
    public function show(string $id, array $types, int $limit): ?array
    {
        return $this->withoutRecording(function () use ($id, $types, $limit): ?array {
            try {
                $entry = $this->repository()->find($id);
            } catch (\Throwable $exception) {
                report($exception);

                return null;
            }

            $batchId = $entry->content['updated_batch_id'] ?? $entry->batchId;
            $batch = [];

            if (is_string($batchId) && $batchId !== '') {
                $batch = collect($this->repository()->get(null, EntryQueryOptions::forBatchId($batchId)->limit(ResultLimit::cap($limit))))
                    ->reject(fn (EntryResult $other): bool => $other->id === $entry->id)
                    ->when($types !== [], fn ($entries) => $entries->filter(
                        fn (EntryResult $other): bool => in_array($other->type, $types, true),
                    ))
                    ->map(fn (EntryResult $other): array => $this->present($other, false))
                    ->values()
                    ->all();
            }

            return [
                'entry' => $this->present($entry, true),
                'batch' => $batch,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EntryResult $entry, bool $detailed): array
    {
        $content = $this->safeContent($entry->content, $detailed);

        return [
            'id' => $entry->id,
            'batch_id' => $entry->batchId,
            'type' => $entry->type,
            'family_hash' => $entry->familyHash,
            'created_at' => $entry->createdAt instanceof \DateTimeInterface
                ? $entry->createdAt->format(\DATE_ATOM)
                : (string) $entry->createdAt,
            'summary' => $this->summary($entry->type, $content),
            'content' => $detailed ? $content : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function safeContent(array $content, bool $detailed): array
    {
        unset($content['bindings'], $content['arguments'], $content['headers']['cookie'], $content['headers']['authorization']);

        if (isset($content['sql']) && is_string($content['sql'])) {
            $content['sql'] = $this->redactor->redactSql($content['sql']);
        }

        if (isset($content['trace']) && is_array($content['trace'])) {
            $frames = (int) config('porthole.limits.trace_frames', 8);
            $content['trace'] = array_slice(array_map(function (mixed $frame): array {
                if (! is_array($frame)) {
                    return [];
                }

                return array_intersect_key($frame, array_flip(['file', 'line', 'class', 'function']));
            }, $content['trace']), 0, max(0, $frames));
        }

        if (! $detailed) {
            return $content;
        }

        $redacted = $this->redactor->redact($content);

        if (! is_array($redacted)) {
            return [];
        }

        $uri = $redacted['uri'] ?? null;

        if (is_string($uri) && $this->routes->contains($uri)) {
            $withParameters = $this->redactor->redactKeys($redacted, PackageRoutes::PARAMETER_NAMES);

            if (! is_array($withParameters)) {
                return $redacted;
            }

            $withParameters['uri'] = $this->routeUri($uri);

            return $withParameters;
        }

        return is_array($redacted) ? $redacted : [];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function summary(string $type, array $content): array
    {
        $summary = match ($type) {
            'request' => [
                'method' => $content['method'] ?? null,
                'uri' => $this->routeUri(is_string($content['uri'] ?? null) ? $content['uri'] : null),
                'status' => $content['response_status'] ?? null,
                'duration_ms' => $content['duration'] ?? null,
            ],
            'query' => [
                'sql' => $content['sql'] ?? null,
                'time_ms' => $content['time'] ?? null,
                'slow' => $content['slow'] ?? null,
                'connection' => $content['connection'] ?? null,
            ],
            'exception' => [
                'class' => $content['class'] ?? null,
                'message' => isset($content['message']) && is_string($content['message']) ? $this->redactor->scrub($content['message']) : null,
                'file' => $content['file'] ?? null,
                'line' => $content['line'] ?? null,
            ],
            'job' => [
                'name' => $content['name'] ?? null,
                'queue' => $content['queue'] ?? null,
                'status' => $content['status'] ?? null,
            ],
            'log' => [
                'level' => $content['level'] ?? null,
                'message' => isset($content['message']) && is_string($content['message']) ? $this->redactor->scrub($content['message']) : null,
            ],
            'client_request' => [
                'method' => $content['method'] ?? null,
                'uri' => $this->routeUri(is_string($content['uri'] ?? null) ? $content['uri'] : null),
                'status' => $content['response_status'] ?? null,
                'duration_ms' => $content['duration'] ?? null,
            ],
            default => [
                'name' => $content['name'] ?? $content['command'] ?? $content['key'] ?? null,
            ],
        };

        return array_filter($summary, fn (mixed $value): bool => $value !== null);
    }

    private function routeUri(?string $uri): ?string
    {
        if ($uri === null) {
            return null;
        }

        $scrubbed = $this->redactor->scrub($uri);

        if (! $this->routes->contains($scrubbed)) {
            return $scrubbed;
        }

        $query = strpos($scrubbed, '?');

        return $query === false ? $scrubbed : substr($scrubbed, 0, $query);
    }

    private function repository(): EntriesRepository
    {
        return app(EntriesRepository::class);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withoutRecording(callable $callback): mixed
    {
        if (! class_exists(Telescope::class)) {
            return $callback();
        }

        return Telescope::withoutRecording($callback);
    }
}
