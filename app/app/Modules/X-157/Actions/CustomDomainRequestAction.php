<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\CustomDomainRequest;
use InvalidArgumentException;

class CustomDomainRequestAction
{
    public function handle(int $businessId, string $domain): CustomDomainRequest
    {
        $domain = strtolower($domain);

        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('not a domain');
        }

        return CustomDomainRequest::firstOrCreate(
            ['business_id' => $businessId, 'domain' => $domain],
            ['requested_at' => now()]
        );
    }
}
