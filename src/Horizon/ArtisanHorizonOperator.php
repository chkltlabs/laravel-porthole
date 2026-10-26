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

        if ($repository->findFailed($id) === null) {
            return false;
        }

        $this->app->make(Dispatcher::class)->dispatch(
            new RetryFailedJob($id),
        );

        return true;
    }
}
