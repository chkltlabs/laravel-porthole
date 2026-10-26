<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use RuntimeException;

final class OauthException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $description,
        public readonly int $status = 400,
        public readonly ?string $redirectUri = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($description);
    }
}
