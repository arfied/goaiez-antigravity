<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Actions;

use App\Modules\CBilling\Models\TrialLimit;

final class TrialRateChangeAction
{
    public function handle(int $businessId, int $newRate): void
    {
        TrialLimit::where('business_id', $businessId)
            ->firstOrFail()
            ->update(['rate_cents_per_min' => $newRate]);
    }
}
