<?php

declare(strict_types=1);

namespace App\Modules\X137\Actions;

use App\Modules\X137\Models\LinkClick;
use App\Modules\X137\Models\ShortLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class LinkRedirectAction
{
    public function handle(int $businessId, string $code, ?string $ip, ?string $userAgent): RedirectResponse
    {
        $link = ShortLink::where('business_id', $businessId)->where('short_code', $code)->firstOrFail();

        try {
            LinkClick::create([
                'business_id' => $businessId,
                'short_link_id' => $link->id,
                'ip_address' => $ip ?? '127.0.0.1',
                'user_agent' => $userAgent ?? 'Unknown',
                'clicked_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            // Tracking failure is never allowed to become a broken link
        }

        return redirect()->away($link->destination_url);
    }
}
