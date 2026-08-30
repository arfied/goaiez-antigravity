<?php

declare(strict_types=1);

namespace App\Modules\X114\Actions;

use App\Modules\X114\Models\BrandKit;

final class MediaBrandAction
{
    public function updateBrandKit(
        int $businessId,
        string $primaryColor,
        string $secondaryColor,
        ?string $logoUrl = null,
        string $fontFamily = 'Inter'
    ): BrandKit {
        return BrandKit::updateOrCreate(
            ['business_id' => $businessId],
            [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
                'logo_url' => $logoUrl,
                'font_family' => $fontFamily,
            ]
        );
    }
}
