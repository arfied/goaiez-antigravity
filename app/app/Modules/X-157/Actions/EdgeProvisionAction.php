<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\EdgeZone;
use Illuminate\Support\Str;

final class EdgeProvisionAction
{
    public function handle(int $businessId, string $domainName, bool $issueSsl = true): EdgeZone
    {
        return EdgeZone::updateOrCreate(
            ['business_id' => $businessId, 'domain_name' => $domainName],
            [
                'provider' => 'cloudflare',
                'zone_id' => 'cf_zone_'.Str::random(12),
                'has_valid_ssl' => $issueSsl,
                'ssl_certificate_id' => $issueSsl ? 'cert_cf_'.Str::random(16) : null,
                'status' => $issueSsl ? 'active' : 'pending_ssl',
            ]
        );
    }
}
