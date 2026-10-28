<?php

declare(strict_types=1);

use Chkltlabs\Porthole\PortholeServer;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Chkltlabs\Porthole\Tools\Logs\DeleteLogFilesTool;
use Chkltlabs\Porthole\Tools\Logs\ListLogFilesTool;
use Chkltlabs\Porthole\Tools\Logs\ReadLogFileTool;
use Chkltlabs\Porthole\Tools\Logs\SearchLogFilesTool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('viewPorthole', fn (): bool => true);
    Gate::define('viewLogs', fn (): bool => true);
    File::cleanDirectory(storage_path('logs'));
    config([
        'porthole.logs.lines' => 10,
        'porthole.logs.lines_max' => 50,
        'porthole.logs.bytes_max' => 4096,
        'porthole.logs.search_matches' => 10,
        'porthole.logs.search_matches_max' => 25,
    ]);
});

afterEach(function () {
    File::cleanDirectory(storage_path('logs'));
});

it('lists log files through the mcp tool', function () {
    File::put(storage_path('logs/laravel.log'), "hello\n");
    File::ensureDirectoryExists(storage_path('logs/worker'));
    File::put(storage_path('logs/worker/queue.log'), "queued\n");
    File::put(storage_path('logs/laravel-2026-10-28.log'), "daily\n");

    PortholeServer::actingAs(new PortholeUser)
        ->tool(ListLogFilesTool::class)
        ->assertOk()
        ->assertSee('laravel.log')
        ->assertSee('laravel-2026-10-28.log')
        ->assertSee('worker/queue.log');
});

it('reads and searches log files with auth', function () {
    $body = collect(range(1, 8))->map(fn (int $n): string => "line-{$n} needle")->implode("\n")."\n";
    File::put(storage_path('logs/laravel.log'), $body);

    PortholeServer::actingAs(new PortholeUser)
        ->tool(ReadLogFileTool::class, [
            'path' => 'laravel.log',
            'mode' => 'tail',
            'lines' => 2,
        ])
        ->assertOk()
        ->assertSee('line-7 needle')
        ->assertSee('line-8 needle');

    PortholeServer::actingAs(new PortholeUser)
        ->tool(SearchLogFilesTool::class, [
            'query' => 'line-3',
            'path' => 'laravel.log',
        ])
        ->assertOk()
        ->assertSee('line-3 needle');
});

it('denies log tools without the viewLogs gate', function () {
    Gate::define('viewLogs', fn (): bool => false);
    File::put(storage_path('logs/laravel.log'), "secret\n");

    PortholeServer::actingAs(new PortholeUser)
        ->tool(ListLogFilesTool::class)
        ->assertHasErrors()
        ->assertSee('You are not authorized to read this Porthole source.');
});

it('does not register logs-delete until destructive tools are enabled', function () {
    PortholeServer::actingAs(new PortholeUser)
        ->tool(DeleteLogFilesTool::class, ['paths' => ['laravel.log']])
        ->assertNotRegistered();
});

it('deletes log files only when destructive tools are enabled', function () {
    config(['porthole.destructive_enabled' => true]);
    File::put(storage_path('logs/laravel.log'), "remove me\n");
    File::put(storage_path('logs/keep.log'), "stay\n");

    PortholeServer::actingAs(new PortholeUser)
        ->tool(DeleteLogFilesTool::class, ['paths' => ['laravel.log', 'missing.log']])
        ->assertOk()
        ->assertSee('laravel.log')
        ->assertSee('missing.log');

    expect(file_exists(storage_path('logs/laravel.log')))->toBeFalse()
        ->and(file_exists(storage_path('logs/keep.log')))->toBeTrue();
});

it('refuses traversal through logs-read and logs-delete', function () {
    config(['porthole.destructive_enabled' => true]);
    File::put(storage_path('logs/laravel.log'), "ok\n");
    File::ensureDirectoryExists(storage_path('framework'));
    File::put(storage_path('framework/secret.log'), "outside\n");

    PortholeServer::actingAs(new PortholeUser)
        ->tool(ReadLogFileTool::class, ['path' => '../framework/secret.log'])
        ->assertHasErrors()
        ->assertSee('storage/logs');

    PortholeServer::actingAs(new PortholeUser)
        ->tool(DeleteLogFilesTool::class, ['paths' => ['../framework/secret.log']])
        ->assertHasErrors()
        ->assertSee('storage/logs');

    expect(file_exists(storage_path('framework/secret.log')))->toBeTrue();
});
