<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Horizon;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Jobs\RetryFailedJob;

final class ArtisanHorizonOperator implements HorizonOperator
{
    public function __construct(private Application $app) {}

    public function pause(): void
    {
        $this->run('horizon:pause');
    }

    public function resume(): void
    {
        $this->run('horizon:continue');
    }

    private function run(string $command): void
    {
        $exitCode = Artisan::call($command);

        if ($exitCode === 0) {
            return;
        }

        $output = trim(Artisan::output());

        throw new HorizonCommandFailed($output !== '' ? $output : $command.' failed.');
    }

    public function retryFailedJob(string $id): bool
    {
        $repository = $this->app->make(JobRepository::class);
        $job = $repository->findFailed($id);

        if ($job === null) {
            return false;
        }

        if (! $this->retryAllowed($this->displayName($job))) {
            throw new HorizonCommandFailed('This job is not on the retry allowlist.');
        }

        $this->app->make(Dispatcher::class)->dispatch(
            new RetryFailedJob($id),
        );

        return true;
    }

    private function displayName(object $job): ?string
    {
        $payload = $job->payload ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (is_array($payload) && isset($payload['displayName']) && is_string($payload['displayName']) && $payload['displayName'] !== '') {
            return $payload['displayName'];
        }

        if (isset($job->name) && is_string($job->name) && $job->name !== '') {
            return $job->name;
        }

        return null;
    }

    private function retryAllowed(?string $name): bool
    {
        if ($name === null || $name === '') {
            return false;
        }

        $allowlist = config('porthole.writes.retry_allowlist', []);

        if (! is_array($allowlist)) {
            return false;
        }

        return in_array($name, $allowlist, true);
    }
}
