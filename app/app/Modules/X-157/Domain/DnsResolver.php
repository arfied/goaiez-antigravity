<?php

namespace App\Modules\X157\Domain;

interface DnsResolver
{
    public function cname(string $host): ?string;
}
