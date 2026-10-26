<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp;

use Chkltlabs\ObservabilityMcp\Tools\Horizon\ContinueHorizonTool;
use Chkltlabs\ObservabilityMcp\Tools\Horizon\HorizonJobsTool;
use Chkltlabs\ObservabilityMcp\Tools\Horizon\HorizonMetricsTool;
use Chkltlabs\ObservabilityMcp\Tools\Horizon\HorizonStatusTool;
use Chkltlabs\ObservabilityMcp\Tools\Horizon\PauseHorizonTool;
use Chkltlabs\ObservabilityMcp\Tools\Horizon\RetryFailedHorizonJobTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseExceptionsTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseHealthTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseOverviewTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseQueuesTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseServersTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseSlowJobsTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseSlowOutgoingRequestsTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseSlowQueriesTool;
use Chkltlabs\ObservabilityMcp\Tools\Pulse\PulseSlowRequestsTool;
use Chkltlabs\ObservabilityMcp\Tools\Telescope\ListTelescopeEntriesTool;
use Chkltlabs\ObservabilityMcp\Tools\Telescope\PruneTelescopeEntriesTool;
use Chkltlabs\ObservabilityMcp\Tools\Telescope\ShowTelescopeEntryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Laravel Observability')]
#[Version('0.1.0')]
#[Instructions(<<<'MARKDOWN'
    Read Horizon, Pulse, and Telescope through authenticated tools.
    Tools are read-only unless the host application enables writes.
    Horizon job payloads and Telescope query bindings are omitted.
    A missing package omits that package's tools.
    MARKDOWN)]
class ObservabilityServer extends Server
{
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
