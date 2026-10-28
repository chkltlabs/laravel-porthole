<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

interface FilamentHost
{
    public function available(): bool;

    public function hasPanel(string $id): bool;

    public function defaultPanelId(): ?string;
}
