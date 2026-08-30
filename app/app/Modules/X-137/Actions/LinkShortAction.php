<?php

declare(strict_types=1);

namespace App\Modules\X137\Actions;

use App\Modules\X137\Models\ShortLink;
use Illuminate\Support\Str;

final class LinkShortAction
{
    public function handle(int $businessId, string $destinationUrl, ?string $campaign = null): ShortLink
    {
        $shortCode = Str::random(6);

        return ShortLink::create([
            'business_id' => $businessId,
            'short_code' => $shortCode,
            'destination_url' => $destinationUrl,
            'campaign' => $campaign,
        ]);
    }
}
