<?php

namespace App\Support;

class WebhookSignature
{
    public const HEADER = 'X-Signature';

    public static function sign(string $payload, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $secret, ?string $signature): bool
    {
        return $signature !== null && hash_equals(self::sign($payload, $secret), $signature);
    }
}
