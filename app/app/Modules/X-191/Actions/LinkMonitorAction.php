<?php

declare(strict_types=1);

namespace App\Modules\X191\Actions;

use App\Modules\X191\Models\LinkPlacement;

final class LinkMonitorAction
{
    /**
     * Placement monitoring (G8-34).
     */
    public function recordPlacement(
        int $businessId,
        string $placedUrl,
        string $anchorText,
        ?int $pitchId = null
    ): LinkPlacement {
        return LinkPlacement::create([
            'business_id' => $businessId,
            'pitch_id' => $pitchId,
            'placed_url' => $placedUrl,
            'anchor_text' => $anchorText,
            'is_active' => true,
            'last_monitored_at' => now(),
        ]);
    }
}
