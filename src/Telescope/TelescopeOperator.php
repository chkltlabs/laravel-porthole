<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Telescope;

interface TelescopeOperator
{
    public function prune(int $hours, bool $keepExceptions): int;
}
