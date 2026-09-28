<?php

namespace App\Services\Fetch;

class PublicAddressGuard
{
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if ($ip === '0.0.0.0') {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            // 100.64.0.0/10 carrier-grade NAT
            $net = ip2long('100.64.0.0');
            $mask = -1 << (32 - 10);
            if (($long & $mask) === ($net & $mask)) {
                return false;
            }
        }

        return true;
    }

    public function check(string $url): ?array
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        if (! isset($parts['host'])) {
            return null;
        }

        $host = $parts['host'];
        $isIpLiteral = false;
        $ipToCheck = $host;

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $ipToCheck = substr($host, 1, -1);
            $isIpLiteral = true;
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $isIpLiteral = true;
        }

        if ($isIpLiteral) {
            if (! self::isPublicIp($ipToCheck)) {
                return null;
            }

            return [$ipToCheck];
        }

        $ips = $this->resolve($host);

        if (empty($ips)) {
            return [];
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return null;
            }
        }

        return $ips;
    }

    protected function resolve(string $host): array
    {
        $ips = [];
        $v4 = gethostbynamel($host);
        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }
}
