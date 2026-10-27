<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Support\EnvBoolean;
use Chkltlabs\Porthole\Support\Redactor;

it('keeps the secure default when a boolean env value is unrecognized', function () {
    expect(EnvBoolean::parse('false', true))->toBeFalse()
        ->and(EnvBoolean::parse('true', false))->toBeTrue()
        ->and(EnvBoolean::parse('maybe', true))->toBeTrue()
        ->and(EnvBoolean::parse('maybe', false))->toBeFalse()
        ->and(EnvBoolean::parse(null, true))->toBeTrue();
});

it('redacts sensitive keys and masks sql literals', function () {
    $redactor = new Redactor(maxStringLength: 80);

    $redacted = $redactor->redact([
        'password' => 'hunter2',
        'note' => 'visible',
    ]);

    expect($redacted['password'])->toBe('[redacted]')
        ->and($redacted['note'])->toBe('visible')
        ->and($redactor->redact(['email' => 'a@b.com'])['email'])->toBe('[redacted]')
        ->and($redactor->redactSql("select * from users where email = 'a@b.com'"))
        ->toBe('select * from users where email = ?')
        ->and($redactor->redactSql('select * from accounts where user_id = 12345'))
        ->toBe('select * from accounts where user_id = ?')
        ->and($redactor->scrub('https://hooks.test/plaid?token=secret'))
        ->toBe('https://hooks.test/plaid')
        ->and($redactor->scrub('/porthole/oauth/authorize?code=auth-code&redirect_uri=https://app.test/callback'))
        ->toBe('/porthole/oauth/authorize?code=auth-code&redirect_uri=https://app.test/callback')
        ->and($redactor->redact([
            'code' => 'auth-code',
            'redirect_uri' => 'https://app.test/callback',
            'scope' => 'mcp:use',
        ]))->toBe([
            'code' => 'auth-code',
            'redirect_uri' => 'https://app.test/callback',
            'scope' => 'mcp:use',
        ]);
});
