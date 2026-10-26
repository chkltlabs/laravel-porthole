<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Horizon;

interface HorizonOperator
{
    public function pause(): void;

    public function resume(): void;

    public function retryFailedJob(string $id): bool;
}
