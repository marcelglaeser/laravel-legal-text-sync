<?php

namespace App\Support;

use App\Exceptions\UnsafeEndpointException;

class PublicEndpoint
{
    private function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $ip,
    ) {}

    public static function resolve(string $url): self
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || ! isset($parts['host'])) {
            throw new UnsafeEndpointException('Only http and https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeEndpointException('URLs must not contain credentials.');
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::lookup($host);

        if ($ips === []) {
            throw new UnsafeEndpointException("The host {$host} could not be resolved.");
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new UnsafeEndpointException("The host {$host} resolves to a non-public address.");
            }
        }

        return new self($host, $parts['port'] ?? ($scheme === 'https' ? 443 : 80), $ips[0]);
    }

    public function isIpLiteral(): bool
    {
        return $this->host === $this->ip;
    }

    public function curlResolveEntry(): string
    {
        $ip = str_contains($this->ip, ':') ? "[{$this->ip}]" : $this->ip;

        return "{$this->host}:{$this->port}:{$ip}";
    }

    /**
     * @return list<string>
     */
    private static function lookup(string $host): array
    {
        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }
}
