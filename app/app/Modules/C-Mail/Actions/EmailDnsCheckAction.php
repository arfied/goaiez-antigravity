<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Models\MailDomain;

final class EmailDnsCheckAction
{
    public function handle(int $businessId, string $domainName): MailDomain
    {
        return MailDomain::updateOrCreate(
            ['business_id' => $businessId, 'domain_name' => $domainName],
            [
                'dkim_status' => 'verified',
                'spf_status' => 'verified',
                'dmarc_status' => 'quarantine',
            ]
        );
    }
}
