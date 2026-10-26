<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\PortholeServer;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Chkltlabs\Porthole\Tools\Horizon\HorizonJobsTool;
use Chkltlabs\Porthole\Tools\Horizon\HorizonStatusTool;
use Chkltlabs\Porthole\Tools\Horizon\PauseHorizonTool;
use Chkltlabs\Porthole\Tools\Horizon\RetryFailedHorizonJobTool;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Mcp\Request;

beforeEach(function () {
    Gate::define('viewPorthole', fn (): bool => true);
    Gate::define('viewHorizon', fn (): bool => true);

    $jobs = Mockery::mock(JobRepository::class);
    $jobs->shouldReceive('countRecent')->andReturn(4)->byDefault();
    $jobs->shouldReceive('countPending')->andReturn(1)->byDefault();
    $jobs->shouldReceive('countCompleted')->andReturn(2)->byDefault();
    $jobs->shouldReceive('countFailed')->andReturn(0)->byDefault();
    $jobs->shouldReceive('countRecentlyFailed')->andReturn(0)->byDefault();
    $jobs->shouldReceive('getRecent')->andReturn(collect([
        (object) [
            'id' => 'job-1',
            'connection' => 'redis',
            'queue' => 'default',
            'status' => 'completed',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\SyncAccount',
                'data' => ['command' => 'serialized-secret'],
            ]),
        ],
    ]))->byDefault();

    $masters = Mockery::mock(MasterSupervisorRepository::class);
    $masters->shouldReceive('all')->andReturn([
        (object) ['name' => 'app', 'status' => 'running', 'pid' => 99],
    ])->byDefault();

    $supervisors = Mockery::mock(SupervisorRepository::class);
    $supervisors->shouldReceive('all')->andReturn([
        (object) ['name' => 'app:supervisor', 'status' => 'running', 'processes' => [['command' => 'php artisan queue:work --secret']]],
    ])->byDefault();

    $workload = Mockery::mock(WorkloadRepository::class);
    $workload->shouldReceive('get')->andReturn([
        ['name' => 'default', 'length' => 3, 'wait' => 0, 'processes' => 1, 'command' => 'do-not-leak'],
    ])->byDefault();

    $metrics = Mockery::mock(MetricsRepository::class);
    $metrics->shouldReceive('jobsProcessedPerMinute')->andReturn(12)->byDefault();

    app()->instance(JobRepository::class, $jobs);
    app()->instance(MasterSupervisorRepository::class, $masters);
    app()->instance(SupervisorRepository::class, $supervisors);
    app()->instance(WorkloadRepository::class, $workload);
    app()->instance(MetricsRepository::class, $metrics);
});

it('returns horizon status without process commands or supervisor pids', function () {
    $response = PortholeServer::actingAs(new PortholeUser)
        ->tool(HorizonStatusTool::class);

    $response->assertOk()
        ->assertSee('running')
        ->assertSee('default')
        ->assertDontSee('do-not-leak')
        ->assertDontSee('queue:work');
});

it('lists job names and omits serialized commands', function () {
    $response = PortholeServer::actingAs(new PortholeUser)
        ->tool(HorizonJobsTool::class, ['status' => 'recent']);

    $response->assertOk()
        ->assertSee('SyncAccount')
        ->assertDontSee('serialized-secret');
});

it('refuses horizon data when the horizon gate denies the account', function () {
    Gate::define('viewHorizon', fn (): bool => false);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(HorizonStatusTool::class)
        ->assertSee('not authorized to read this Porthole source');
});

it('does not register horizon pause while writes are disabled', function () {
    PortholeServer::actingAs(new PortholeUser)
        ->tool(PauseHorizonTool::class)
        ->assertNotRegistered();
});

it('pauses horizon only after writes are enabled', function () {
    config(['porthole.writes_enabled' => true]);

    $operator = new class implements HorizonOperator
    {
        public int $pauses = 0;

        public function pause(): void
        {
            $this->pauses++;
        }

        public function resume(): void {}

        public function retryFailedJob(string $id): bool
        {
            return false;
        }
    };

    app()->instance(HorizonOperator::class, $operator);

    Gate::define('mutateHorizon', fn (): bool => true);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PauseHorizonTool::class)
        ->assertOk()
        ->assertSee('paused');

    expect($operator->pauses)->toBe(1);
});

it('refuses to pause horizon when mutateHorizon is not defined', function () {
    config(['porthole.writes_enabled' => true]);

    $operator = new class implements HorizonOperator
    {
        public int $pauses = 0;

        public function pause(): void
        {
            $this->pauses++;
        }

        public function resume(): void {}

        public function retryFailedJob(string $id): bool
        {
            return false;
        }
    };

    app()->instance(HorizonOperator::class, $operator);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PauseHorizonTool::class)
        ->assertSee('not authorized to change');

    expect($operator->pauses)->toBe(0);
});

it('reports a horizon pause failure when artisan exits non-zero', function () {
    config(['porthole.writes_enabled' => true]);
    Gate::define('mutateHorizon', fn (): bool => true);
    bindArtisan(exitCode: 1, output: 'no masters');

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PauseHorizonTool::class)
        ->assertSee('no masters');
});

it('pauses horizon when artisan exits cleanly', function () {
    config(['porthole.writes_enabled' => true]);
    Gate::define('mutateHorizon', fn (): bool => true);
    bindArtisan(exitCode: 0, output: '');

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PauseHorizonTool::class)
        ->assertOk()
        ->assertSee('paused');
});

it('skips tool gates on stdio when stdio auth is disabled', function () {
    config([
        'porthole.writes_enabled' => true,
        'porthole.auth.http_required' => true,
        'porthole.auth.stdio_required' => false,
    ]);

    $operator = new class implements HorizonOperator
    {
        public int $pauses = 0;

        public function pause(): void
        {
            $this->pauses++;
        }

        public function resume(): void {}

        public function retryFailedJob(string $id): bool
        {
            return false;
        }
    };

    app()->instance(HorizonOperator::class, $operator);
    app()->instance('porthole.transport', 'stdio');

    app(PauseHorizonTool::class)->handle(new Request);

    expect($operator->pauses)->toBe(1);
});

it('keeps http gates when a tool call has no stdio transport marker', function () {
    config([
        'porthole.writes_enabled' => true,
        'porthole.auth.http_required' => true,
        'porthole.auth.stdio_required' => false,
    ]);

    $operator = new class implements HorizonOperator
    {
        public int $pauses = 0;

        public function pause(): void
        {
            $this->pauses++;
        }

        public function resume(): void {}

        public function retryFailedJob(string $id): bool
        {
            return false;
        }
    };

    app()->instance(HorizonOperator::class, $operator);
    app()->forgetInstance('porthole.transport');

    app(PauseHorizonTool::class)->handle(new Request);

    expect($operator->pauses)->toBe(0);
});

it('refuses to retry a failed job unless its display name is allowlisted', function () {
    config([
        'porthole.writes_enabled' => true,
        'porthole.writes.retry_allowlist' => [],
    ]);
    Gate::define('mutateHorizon', fn (): bool => true);

    $jobs = app(JobRepository::class);
    $jobs->shouldReceive('findFailed')->andReturn((object) [
        'id' => 'job-1',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncAccount']),
    ]);

    $dispatches = 0;
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andReturnUsing(function () use (&$dispatches): null {
        $dispatches++;

        return null;
    });
    app()->instance(Dispatcher::class, $dispatcher);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(RetryFailedHorizonJobTool::class, ['id' => 'job-1'])
        ->assertSee('allowlist');

    config(['porthole.writes.retry_allowlist' => ['App\\Jobs\\SendMail']]);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(RetryFailedHorizonJobTool::class, ['id' => 'job-1'])
        ->assertSee('allowlist');

    expect($dispatches)->toBe(0);

    config(['porthole.writes.retry_allowlist' => ['App\\Jobs\\SyncAccount']]);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(RetryFailedHorizonJobTool::class, ['id' => 'job-1'])
        ->assertOk()
        ->assertSee('retried');

    expect($dispatches)->toBe(1);
});

it('does not call the horizon operator when a write tool is invoked while writes are disabled', function () {
    $operator = new class implements HorizonOperator
    {
        public int $pauses = 0;

        public function pause(): void
        {
            $this->pauses++;
        }

        public function resume(): void {}

        public function retryFailedJob(string $id): bool
        {
            return false;
        }
    };

    app()->instance(HorizonOperator::class, $operator);
    $this->actingAs(new PortholeUser);

    app(PauseHorizonTool::class)->handle(new Request);

    expect($operator->pauses)->toBe(0);
});

function bindArtisan(int $exitCode, string $output): void
{
    app()->instance(Kernel::class, new class($exitCode, $output) implements Kernel
    {
        public function __construct(private int $exitCode, private string $output) {}

        public function bootstrap() {}

        public function handle($input, $output = null)
        {
            return $this->exitCode;
        }

        public function call($command, array $parameters = [], $outputBuffer = null)
        {
            return $this->exitCode;
        }

        public function queue($command, array $parameters = [])
        {
            return null;
        }

        public function all()
        {
            return [];
        }

        public function output()
        {
            return $this->output;
        }

        public function terminate($input, $status) {}
    });

    Artisan::clearResolvedInstances();
}
