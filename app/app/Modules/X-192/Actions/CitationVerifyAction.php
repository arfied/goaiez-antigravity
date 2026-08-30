<?php

declare(strict_types=1);

namespace App\Modules\X192\Actions;

use App\Modules\X192\Events\CitationVerified;
use App\Modules\X192\Models\Citation;
use Illuminate\Support\Facades\Event;

final class CitationVerifyAction
{
    public function verifyCitation(
        int $businessId,
        string $businessName,
        string $phone,
        string $address,
        ?int $membershipId = null
    ): Citation {
        $citation = Citation::create([
            'business_id' => $businessId,
            'membership_id' => $membershipId,
            'nap_business_name' => $businessName,
            'nap_phone' => $phone,
            'nap_address' => $address,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        Event::dispatch(new CitationVerified($businessId, $citation->id, true));

        return $citation;
    }
}
