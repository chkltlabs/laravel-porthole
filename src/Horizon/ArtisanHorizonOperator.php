<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Horizon;

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
        Artisan::call('horizon:pause');
    }

    public function resume(): void
    {
        Artisan::call('horizon:continue');
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
