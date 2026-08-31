<?php

declare(strict_types=1);

namespace App\Modules\X191\Actions;

use App\Modules\X191\Events\CompetitorAnalysed;
use App\Modules\X191\Models\LinkTarget;
use Illuminate\Support\Facades\Event;

final class LinkProspectAction
{
    public function prospectDomain(
        int $businessId,
        string $domain,
        string $targetUrl,
        bool $isPbn = false,
        int $daScore = 35
    ): LinkTarget {
        $target = LinkTarget::create([
            'business_id' => $businessId,
            'domain' => $domain,
            'target_url' => $targetUrl,
            'is_pbn' => $isPbn,
            'domain_authority' => $daScore,
        ]);

        Event::dispatch(new CompetitorAnalysed($businessId, $domain, 1));

        return $target;
    }
}
