<?php

declare(strict_types=1);

use Chkltlabs\ObservabilityMcp\ObservabilityServer;
use Chkltlabs\ObservabilityMcp\Tests\ObservabilityUser;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseServersTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseSlowQueriesTool;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Contracts\Storage;

beforeEach(function () {
    Gate::define('viewObservabilityMcp', fn (): bool => true);
    Gate::define('viewPulse', fn (): bool => true);

    $storage = Mockery::mock(Storage::class);
    $storage->shouldReceive('aggregate')->andReturn(collect([
        (object) [
            'key' => "select * from users where email = 'secret@example.com'",
            'count' => 2,
            'max' => 90,
            'avg' => 40,
        ],
    ]))->byDefault();
    $storage->shouldReceive('aggregateTotal')->andReturn(2)->byDefault();
    $storage->shouldReceive('values')->andReturn(collect([
        (object) [
            'key' => 'web-1',
            'timestamp' => 1,
            'value' => json_encode([
                'name' => 'web-1',
                'cpu' => 12,
                'password' => 'hunter2',
            ]),
        ],
    ]))->byDefault();

    app()->instance(Storage::class, $storage);
});

it('masks sql literals in slow queries and filters in php', function () {
    $response = ObservabilityServer::actingAs(new ObservabilityUser)
        ->tool(PulseSlowQueriesTool::class, [
            'period' => '1h',
            'search' => 'users',
        ]);

    $response->assertOk()
        ->assertSee('select * from users')
        ->assertDontSee('secret@example.com');
});

it('redacts sensitive server fields', function () {
    $response = ObservabilityServer::actingAs(new ObservabilityUser)
        ->tool(PulseServersTool::class);

    $response->assertOk()
        ->assertSee('web-1')
        ->assertDontSee('hunter2');
});

it('rejects a pulse period outside the windows pulse aggregates', function () {
    ObservabilityServer::actingAs(new ObservabilityUser)
        ->tool(PulseSlowQueriesTool::class, ['period' => '30d'])
        ->assertHasErrors();
});
