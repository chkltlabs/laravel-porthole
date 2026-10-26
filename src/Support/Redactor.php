<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Support;

final class Redactor
{
    public function __construct(
        private int $maxStringLength = 500,
        private int $maxDepth = 6,
    ) {}

    public function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > $this->maxDepth) {
            return '[truncated]';
        }

        if (is_string($value)) {
            return $this->scrub($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = $this->redact($item, $depth + 1);
        }

        return $redacted;
    }

    public function redactSql(string $sql): string
    {
        $masked = preg_replace("/'(?:\\\\'|[^'])*'/", '?', $sql) ?? $sql;
        $masked = preg_replace('/"(?:\\\\"|[^"])*"/', '?', $masked) ?? $masked;
        $masked = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $masked) ?? $masked;

        return $this->scrub($masked);
    }

    public function scrub(string $value): string
    {
        $stripped = preg_replace_callback(
            '/\bhttps?:\/\/[^\s]+/i',
            static function (array $match): string {
                $url = $match[0];
                $query = strpos($url, '?');

                if ($query === false) {
                    return $url;
                }

                return substr($url, 0, $query);
            },
            $value,
        ) ?? $value;

        return $this->truncate($stripped);
    }

    public function truncate(string $value): string
    {
        if (mb_strlen($value) <= $this->maxStringLength) {
            return $value;
        }

        return mb_substr($value, 0, $this->maxStringLength).'…';
    }

    public function isSensitiveKey(string $key): bool
    {
        $normalized = str_replace(['-', ' '], '_', strtolower($key));

        foreach ([
            'password',
            'passwd',
            'secret',
            'token',
            'authorization',
            'cookie',
            'api_key',
            'apikey',
            'access_token',
            'refresh_token',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'email',
            'account_number',
            'routing_number',
            'private_key',
            'php_auth_pw',
            'x_csrf_token',
            'x_xsrf_token',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
