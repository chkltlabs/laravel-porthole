<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth\Filament;

use Filament\Actions\Action;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ConsentPage extends SimplePage
{
    public const FORM_ID = 'porthole-oauth-consent';

    protected string $view = 'porthole::oauth.filament-form';

    public string $clientName = '';

    public string $redirectHost = '';

    public string $requestId = '';

    public function getTitle(): string
    {
        return 'Authorize '.$this->clientName;
    }

    public function getHeading(): string
    {
        return $this->getTitle();
    }

    public function getSubheading(): Htmlable
    {
        return new HtmlString(
            'This client is asking for the <code>mcp:use</code> scope on the Porthole issuer. '
            .'The token it receives is not an application API token. '
            .'Redirect host: <code>'.e($this->redirectHost).'</code>'
        );
    }

    /**
     * @return list<Action>
     */
    public function decisionActions(): array
    {
        return [
            $this->decisionAction('approve', 'Approve', 'primary'),
            $this->decisionAction('deny', 'Deny', 'gray'),
        ];
    }

    private function decisionAction(string $decision, string $label, string $color): Action
    {
        return Action::make($decision)
            ->label($label)
            ->button()
            ->color($color)
            ->submit(self::FORM_ID)
            ->livewireClickHandlerEnabled(false)
            ->extraAttributes([
                'name' => 'decision',
                'value' => $decision,
            ]);
    }
}
