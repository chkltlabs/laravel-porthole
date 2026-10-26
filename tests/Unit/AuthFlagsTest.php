<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Support\AuthFlags;

it('keeps authentication required when both flags are unset', function () {
    expect(AuthFlags::resolve(null, null))->toBeTrue()
        ->and(AuthFlags::resolve('', null))->toBeTrue();
});

it('lets a transport-specific flag override the legacy switch', function () {
    expect(AuthFlags::resolve('true', 'false'))->toBeTrue()
        ->and(AuthFlags::resolve('false', 'true'))->toBeFalse();
});

it('applies the legacy switch only when the transport flag is absent', function () {
    expect(AuthFlags::resolve(null, 'false'))->toBeFalse()
        ->and(AuthFlags::resolve('', 'false'))->toBeFalse();
});
