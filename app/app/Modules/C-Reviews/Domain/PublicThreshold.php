<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Domain;

use App\Modules\CReviews\Models\QaSetting;

final class PublicThreshold
{
    /** P-110 `public_threshold`, default 4, max 5. The row is qa_settings.min_public_stars. */
    public const FALLBACK = 4;

    public function for(int $businessId): int
    {
        $setting = QaSetting::where('business_id', $businessId)->first();

        return $setting ? (int) $setting->min_public_stars : self::FALLBACK;
    }
}
