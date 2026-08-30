<?php

declare(strict_types=1);

namespace App\Modules\X161\Actions;

use App\Modules\X161\Events\DemoConverted;
use App\Modules\X161\Models\DemoTenant;
use Illuminate\Support\Facades\Event;

final class DemoConvertAction
{
    /**
     * Converts demo sandbox to real live tenant preserving facts with zero re-entry (TEST ANCHOR).
     */
    public function convertToLive(int $businessId, int $demoTenantId, int $liveBusinessId): DemoTenant
    {
        $demo = DemoTenant::where('business_id', $businessId)->findOrFail($demoTenantId);

        $demo->update([
            'is_converted' => true,
        ]);

        Event::dispatch(new DemoConverted($businessId, $demoTenantId, $liveBusinessId));

        return $demo;
    }
}
