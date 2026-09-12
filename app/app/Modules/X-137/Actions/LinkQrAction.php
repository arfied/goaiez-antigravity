<?php

declare(strict_types=1);

namespace App\Modules\X137\Actions;

use App\Modules\X137\Models\ShortLink;

final class LinkQrAction
{
    public function handle(int $businessId, int $shortLinkId): string
    {
        // short_links is readable by every business under the platform's public_read policy, so this business_id clause
        // is the only tenancy check on this read; the redirect route in ../ModuleServiceProvider.php records the measurement.
        $link = ShortLink::where('business_id', $businessId)->findOrFail($shortLinkId);
        $qrSvg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' fill='#000'/></svg>";

        $link->update(['qr_svg' => $qrSvg]);

        return $qrSvg;
    }
}
