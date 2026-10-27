<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Support;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Passport\Passport;

/**
 * laravel/mcp registers an mcp:use Passport scope from a booted callback.
 * Every 1.x release through 1.1.2 does this, so a version constraint cannot
 * exclude it. This drops that scope again when the application did not
 * register it first.
 */
final class PassportScopes
{
    public static function preserve(Application $app): void
    {
        $app->booted(function () use ($app): void {
            if (! class_exists(Passport::class)) {
                return;
            }

            /** @var array<string, string> $before */
            $before = Passport::$scopes;

            self::afterQueuedBootCallbacks($app, static function () use ($before): void {
                $scopes = Passport::$scopes;

                if (! is_array($scopes) || array_key_exists('mcp:use', $before) || ! array_key_exists('mcp:use', $scopes)) {
                    return;
                }

                unset($scopes['mcp:use']);
                Passport::$scopes = $scopes;
            });
        });
    }

    /**
     * Application::booted() runs a new callback immediately once the app is
     * booted, which is before laravel/mcp's callback later in the same list.
     * Appending to that list lets the queued callbacks finish first.
     */
    private static function afterQueuedBootCallbacks(Application $app, callable $callback): void
    {
        $append = Closure::bind(function (callable $callback): void {
            $this->bootedCallbacks[] = $callback;
        }, $app, $app::class);

        if ($append === null) {
            return;
        }

        $append($callback);
    }
}
