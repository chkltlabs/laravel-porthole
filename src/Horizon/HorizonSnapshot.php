<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Horizon;

use Chkltlabs\Porthole\Support\Redactor;
use Chkltlabs\Porthole\Support\ResultLimit;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

final class HorizonSnapshot
{
    public function __construct(private Redactor $redactor) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $masters = collect($this->repository(MasterSupervisorRepository::class)->all());
        $supervisors = collect($this->repository(SupervisorRepository::class)->all());
        $jobs = $this->repository(JobRepository::class);
        $metrics = $this->repository(MetricsRepository::class);

        return [
            'status' => $this->currentStatus($masters),
            'masters' => $masters->map(fn (object $master): array => [
                'name' => $master->name ?? null,
                'status' => $master->status ?? null,
            ])->values()->all(),
            'supervisors' => $supervisors->map(fn (object $supervisor): array => [
                'name' => $supervisor->name ?? null,
                'status' => $supervisor->status ?? null,
                'processes' => collect($supervisor->processes ?? [])->count(),
            ])->values()->all(),
            'workload' => collect($this->repository(WorkloadRepository::class)->get())
                ->map(fn (array $queue): array => [
                    'name' => $queue['name'] ?? null,
                    'length' => $queue['length'] ?? null,
                    'wait' => $queue['wait'] ?? null,
                    'processes' => $queue['processes'] ?? null,
                ])->values()->all(),
            'counts' => [
                'recent' => $jobs->countRecent(),
                'pending' => $jobs->countPending(),
                'completed' => $jobs->countCompleted(),
                'failed' => $jobs->countFailed(),
                'recently_failed' => $jobs->countRecentlyFailed(),
            ],
            'jobs_per_minute' => $metrics->jobsProcessedPerMinute(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jobs(string $status, ?string $after, int $limit): array
    {
        $method = match ($status) {
            'pending' => 'getPending',
            'completed' => 'getCompleted',
            'failed' => 'getFailed',
            'silenced' => 'getSilenced',
            default => 'getRecent',
        };

        $jobs = $this->repository(JobRepository::class)->{$method}($after);

        return [
            'status' => $status === '' ? 'recent' : $status,
            'jobs' => collect($jobs)
                ->take(ResultLimit::cap($limit))
                ->map(fn (object $job): array => $this->job($job))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(int $limit): array
    {
        $metrics = $this->repository(MetricsRepository::class);
        $limit = ResultLimit::cap($limit);

        return [
            'jobs' => collect($metrics->measuredJobs())->take($limit)->map(fn (string $job): array => [
                'job' => $job,
                'throughput' => $metrics->throughputForJob($job),
                'runtime_ms' => $metrics->runtimeForJob($job),
            ])->values()->all(),
            'queues' => collect($metrics->measuredQueues())->take($limit)->map(fn (string $queue): array => [
                'queue' => $queue,
                'throughput' => $metrics->throughputForQueue($queue),
                'runtime_ms' => $metrics->runtimeForQueue($queue),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, object>  $masters
     */
    private function currentStatus(Collection $masters): string
    {
        if ($masters->isEmpty()) {
            return 'inactive';
        }

        return $masters->every(fn (object $master): bool => ($master->status ?? null) === 'paused')
            ? 'paused'
            : 'running';
    }

    /**
     * @return array<string, mixed>
     */
    private function job(object $job): array
    {
        $payload = $this->payload($job->payload ?? null);

        return [
            'id' => $job->id ?? null,
            'name' => $payload['displayName'] ?? ($job->name ?? null),
            'connection' => $job->connection ?? null,
            'queue' => $job->queue ?? null,
            'status' => $job->status ?? null,
            'exception' => isset($job->exception) ? $this->redactor->truncate((string) $job->exception) : null,
            'failed_at' => $job->failed_at ?? null,
            'completed_at' => $job->completed_at ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($payload)) {
            return [];
        }

        return array_intersect_key($payload, array_flip(['displayName', 'maxTries', 'timeout']));
    }

    private function repository(string $contract): mixed
    {
        return app($contract);
    }
}
