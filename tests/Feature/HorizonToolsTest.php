<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\PortholeServer;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Chkltlabs\Porthole\Tools\Horizon\HorizonJobsTool;
use Chkltlabs\Porthole\Tools\Horizon\HorizonStatusTool;
use Chkltlabs\Porthole\Tools\Horizon\PauseHorizonTool;
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

    PortholeServer::actingAs(new PortholeUser)
        ->tool(PauseHorizonTool::class)
        ->assertOk()
        ->assertSee('paused');

    expect($operator->pauses)->toBe(1);
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
