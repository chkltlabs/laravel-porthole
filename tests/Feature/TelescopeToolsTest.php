<?php

declare(strict_types=1);

use Chkltlabs\Porthole\PortholeServer;
use Chkltlabs\Porthole\Telescope\TelescopeOperator;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Chkltlabs\Porthole\Tools\Telescope\ListTelescopeEntriesTool;
use Chkltlabs\Porthole\Tools\Telescope\PruneTelescopeEntriesTool;
use Chkltlabs\Porthole\Tools\Telescope\ShowTelescopeEntryTool;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryResult;

beforeEach(function () {
    Gate::define('viewPorthole', fn (): bool => true);
    Gate::define('viewTelescope', fn (): bool => true);

    $entry = new EntryResult(
        'entry-1',
        1,
        'batch-1',
        'query',
        'family-1',
        [
            'sql' => "select * from users where email = 'secret@example.com'",
            'bindings' => ['secret@example.com'],
            'time' => 12,
            'slow' => true,
            'connection' => 'mysql',
            'password' => 'hunter2',
            'trace' => [
                ['file' => 'app/Http/Controllers/AccountController.php', 'line' => 40, 'args' => ['token' => 'abc']],
            ],
        ],
        now(),
    );

    $repository = Mockery::mock(EntriesRepository::class);
    $repository->shouldReceive('get')->andReturn(collect([$entry]))->byDefault();
    $repository->shouldReceive('find')->andReturn($entry)->byDefault();

    app()->instance(EntriesRepository::class, $repository);
});

it('lists a query summary without bindings', function () {
    $response = PortholeServer::actingAs(new PortholeUser)
        ->tool(ListTelescopeEntriesTool::class, ['type' => 'query']);

    $response->assertOk()
        ->assertSee('select * from users')
        ->assertDontSee('secret@example.com')
        ->assertDontSee('hunter2');
});

it('shows an entry without bindings, secrets, or trace arguments', function () {
    $response = PortholeServer::actingAs(new PortholeUser)
        ->tool(ShowTelescopeEntryTool::class, ['id' => 'entry-1', 'limit' => 5]);

    $response->assertOk()
        ->assertSee('AccountController.php')
        ->assertDontSee('secret@example.com')
        ->assertDontSee('hunter2')
        ->assertDontSee('"args"');
});

it('does not register telescope prune until destructive tools are enabled', function () {
    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class)
        ->assertNotRegistered();
});

it('caps prune retention and calls the operator only when destructive tools are enabled', function () {
    config(['porthole.destructive_enabled' => true, 'porthole.prune_max_hours' => 48]);

    $operator = new class implements TelescopeOperator
    {
        public ?int $hours = null;

        public function prune(int $hours, bool $keepExceptions): int
        {
            $this->hours = $hours;

            return 3;
        }
    };

    app()->instance(TelescopeOperator::class, $operator);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class, ['hours' => 48])
        ->assertOk()
        ->assertSee('pruned');

    expect($operator->hours)->toBe(48);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class, ['hours' => 1])
        ->assertHasErrors();

    expect($operator->hours)->toBe(48);

    config([
        'porthole.prune_min_hours' => 1,
        'porthole.prune_allow_short_retention' => true,
    ]);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class, ['hours' => 1])
        ->assertHasErrors();

    expect($operator->hours)->toBe(48);

    app()->instance('env', 'local');

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class, ['hours' => 1])
        ->assertOk();

    expect($operator->hours)->toBe(1);

    app()->instance('env', 'testing');

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class)
        ->assertOk();

    expect($operator->hours)->toBe(24);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PruneTelescopeEntriesTool::class, ['hours' => 1000])
        ->assertHasErrors();
});
