<?php

namespace App\Modules\X157\Domain;

class SystemDnsResolver implements DnsResolver
{
    public function cname(string $host): ?string
    {
        $records = @dns_get_record($host, DNS_CNAME);
        if ($records === false || count($records) === 0) {
            return null;
        }

        return $records[0]['target'] ?? null;
    }
}
