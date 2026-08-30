<?php

declare(strict_types=1);

namespace App\Modules\X217\Actions;

use App\Modules\X217\Events\SendRequested;
use App\Modules\X217\Models\AffiliateProspect;
use Illuminate\Support\Facades\Event;

final class AffiliateRecruitAction
{
    public function recruitProspect(int $businessId, string $partnerName, string $email): AffiliateProspect
    {
        $prospect = AffiliateProspect::create([
            'business_id' => $businessId,
            'partner_name' => $partnerName,
            'email' => $email,
            'stage' => 'pitched',
        ]);

        Event::dispatch(new SendRequested($businessId, $email));

        return $prospect;
    }
}
