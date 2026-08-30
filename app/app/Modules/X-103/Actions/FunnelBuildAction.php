<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Funnel;
use Carbon\Carbon;

final class FunnelBuildAction
{
    public function handle(
        int $businessId,
        string $name,
        array $steps,
        string $shortSlug,
        ?array $deviceRouting = null,
        ?int $clickCap = null,
        ?Carbon $expiresAt = null
    ): Funnel {
        return Funnel::updateOrCreate(
            ['business_id' => $businessId, 'short_slug' => $shortSlug],
            [
                'name' => $name,
                'steps' => $steps,
                'device_routing' => $deviceRouting,
                'click_cap' => $clickCap,
                'expires_at' => $expiresAt,
            ]
        );
    }
}
