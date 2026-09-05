<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;

final class EmailHaltSeedAction
{
    /** R17 halt seeds — G11-05. Both are inclusive at the boundary; see JOURNAL. */
    public const COMPLAINT_RATE_SEED = 0.0010;   // 0.10%

    public const BOUNCE_COUNT_SEED = 250;

    public function handle(int $businessId, int $mailDomainId): MailDomain
    {
        $domain = MailDomain::where('business_id', $businessId)->findOrFail($mailDomainId);

        $sent = MailEvent::where('business_id', $businessId)
            ->where('mail_domain_id', $mailDomainId)
            ->where('event_type', 'sent')
            ->count();

        $complaints = MailEvent::where('business_id', $businessId)
            ->where('mail_domain_id', $mailDomainId)
            ->where('event_type', 'complained')
            ->count();

        $bounces = MailEvent::where('business_id', $businessId)
            ->where('mail_domain_id', $mailDomainId)
            ->where('event_type', 'bounced')
            ->count();

        $rate = $sent > 0 ? $complaints / $sent : 0.0;

        $domain->complaint_rate = $rate;

        if ($rate >= self::COMPLAINT_RATE_SEED || $bounces >= self::BOUNCE_COUNT_SEED) {
            $domain->is_marketing_paused = true;
        }

        $domain->save();

        return $domain;
    }
}
