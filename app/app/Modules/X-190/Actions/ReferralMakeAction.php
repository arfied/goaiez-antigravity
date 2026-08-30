<?php

declare(strict_types=1);

namespace App\Modules\X190\Actions;

use App\Modules\X190\Models\Referral;
use Illuminate\Support\Str;

final class ReferralMakeAction
{
    /**
     * Generates customer referral link (G7-25, G7-43).
     * "20% off" is tenant offer under R26.
     */
    public function createReferral(
        int $businessId,
        string $customerName,
        ?int $slotId = null,
        string $discountOffer = '20% off next service'
    ): Referral {
        return Referral::create([
            'business_id' => $businessId,
            'slot_id' => $slotId,
            'customer_name' => $customerName,
            'referral_code' => 'REF-'.strtoupper(Str::random(6)),
            'discount_offer' => $discountOffer,
            'package_delivered' => true,
            'partner_bought' => false,
        ]);
    }
}
