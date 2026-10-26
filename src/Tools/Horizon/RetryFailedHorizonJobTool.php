<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Horizon;

use Chkltlabs\Porthole\Horizon\HorizonCommandFailed;
use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\Support\InstalledPackage;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class RetryFailedHorizonJobTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'horizon-retry-failed-job';

    protected string $description = 'Retry one failed Horizon job by id. Registered only when porthole.writes_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('porthole.writes_enabled') === true
            && InstalledPackage::exists(JobRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->mutationDenied($request, 'viewHorizon', 'mutateHorizon')) {
            return $denied;
        }

        if ($denied = $this->writesDenied()) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:100'],
        ]);

        try {
            $retried = app(HorizonOperator::class)->retryFailedJob($validated['id']);
        } catch (HorizonCommandFailed $exception) {
            return Response::error($exception->getMessage());
        }

        if (! $retried) {
            return Response::error('Failed job was not found.');
        }

        return Response::structured([
            'retried' => true,
            'id' => $validated['id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The failed Horizon job id.')->required(),
        ];
    }
}
