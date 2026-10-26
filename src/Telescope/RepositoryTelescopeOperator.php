<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Telescope;

use Illuminate\Contracts\Foundation\Application;
use Laravel\Telescope\Contracts\PrunableRepository;

final class RepositoryTelescopeOperator implements TelescopeOperator
{
    public function __construct(private Application $app) {}

    public function prune(int $hours, bool $keepExceptions): int
    {
        $repository = $this->app->make(PrunableRepository::class);

        return (int) $repository->prune(now()->subHours($hours), $keepExceptions);
    }
}
