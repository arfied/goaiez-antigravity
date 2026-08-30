<?php

declare(strict_types=1);

namespace App\Modules\X190\Actions;

use App\Modules\X190\Models\Referral;

final class PartnerPackageAction
{
    /**
     * Ships partner package.
     * TEST ANCHOR: The partner package ships whether or not the partner ever buys.
     */
    public function shipPackage(int $businessId, int $referralId, bool $partnerBought = false): Referral
    {
        $referral = Referral::where('business_id', $businessId)->findOrFail($referralId);

        $referral->update([
            'package_delivered' => true, // TEST ANCHOR: Delivered regardless of buyer status
            'partner_bought' => $partnerBought,
        ]);

        return $referral;
    }
}
