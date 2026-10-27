<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Support;

use Illuminate\Routing\Route;

final class PackageRoutes
{
    /**
     * OAuth query parameters stored on a request this package registered.
     *
     * @var list<string>
     */
    public const PARAMETER_NAMES = [
        'code',
        'state',
        'nonce',
        'code_verifier',
        'code_challenge',
        'redirect_uri',
        'redirect_url',
        'return_url',
    ];

    public function contains(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = strtok($uri, '?') ?: '';
        }

        $path = trim($path, '/');

        if ($path === '') {
            return false;
        }

        foreach ($this->patterns() as $pattern) {
            if ($this->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function patterns(): array
    {
        $patterns = [];

        foreach (app('router')->getRoutes() as $route) {
            if ($route instanceof Route && $this->registeredByPackage($route)) {
                $patterns[] = $route->uri();
            }
        }

        return $patterns;
    }

    private function registeredByPackage(Route $route): bool
    {
        $name = $route->getName();

        if (is_string($name) && str_starts_with($name, 'porthole.')) {
            return true;
        }

        $action = $route->getActionName();

        if (str_starts_with($action, 'Chkltlabs\\Porthole\\')) {
            return true;
        }

        foreach ($route->middleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'Chkltlabs\\Porthole\\')) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $path, string $pattern): bool
    {
        $expression = preg_quote(trim($pattern, '/'), '#');
        $expression = preg_replace('/\\\\\{[^}]+\\\\\}/', '.*', $expression) ?? $expression;

        return preg_match('#^'.$expression.'$#', $path) === 1;
    }
}
