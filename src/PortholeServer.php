<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole;

use Chkltlabs\Porthole\Tools\Horizon\ContinueHorizonTool;
use Chkltlabs\Porthole\Tools\Horizon\HorizonJobsTool;
use Chkltlabs\Porthole\Tools\Horizon\HorizonMetricsTool;
use Chkltlabs\Porthole\Tools\Horizon\HorizonStatusTool;
use Chkltlabs\Porthole\Tools\Horizon\PauseHorizonTool;
use Chkltlabs\Porthole\Tools\Horizon\RetryFailedHorizonJobTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseExceptionsTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseHealthTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseOverviewTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseQueuesTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseServersTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseSlowJobsTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseSlowOutgoingRequestsTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseSlowQueriesTool;
use Chkltlabs\Porthole\Tools\Pulse\PulseSlowRequestsTool;
use Chkltlabs\Porthole\Tools\Telescope\ListTelescopeEntriesTool;
use Chkltlabs\Porthole\Tools\Telescope\PruneTelescopeEntriesTool;
use Chkltlabs\Porthole\Tools\Telescope\ShowTelescopeEntryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Transport\StdioTransport;

#[Name('Laravel Porthole')]
#[Version(self::VERSION)]
#[Instructions(<<<'MARKDOWN'
    Read Horizon, Pulse, and Telescope through authenticated tools.
    Tools are read-only unless the host application enables writes.
    Horizon job payloads and Telescope query bindings are omitted.
    A missing package omits that package's tools.
    MARKDOWN)]
class PortholeServer extends Server
{
    public const VERSION = '0.6.1';

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        app()->instance(
            'porthole.transport',
            $transport instanceof StdioTransport ? 'stdio' : 'http',
        );
    }

    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        HorizonStatusTool::class,
        HorizonJobsTool::class,
        HorizonMetricsTool::class,
        PauseHorizonTool::class,
        ContinueHorizonTool::class,
        RetryFailedHorizonJobTool::class,
        PulseHealthTool::class,
        PulseOverviewTool::class,
        PulseExceptionsTool::class,
        PulseSlowQueriesTool::class,
        PulseSlowRequestsTool::class,
        PulseSlowJobsTool::class,
        PulseSlowOutgoingRequestsTool::class,
        PulseQueuesTool::class,
        PulseServersTool::class,
        ListTelescopeEntriesTool::class,
        ShowTelescopeEntryTool::class,
        PruneTelescopeEntriesTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
