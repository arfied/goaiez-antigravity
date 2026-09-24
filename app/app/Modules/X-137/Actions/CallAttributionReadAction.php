<?php

declare(strict_types=1);

namespace App\Modules\X137\Actions;

use App\Modules\X137\Models\CallToken;

final class CallAttributionReadAction
{
    /** Calls joined to a website session (a tracked-number call) created since $since. */
    public function joinedSince(int $businessId, \DateTimeInterface $since): int
    {
        return CallToken::where('business_id', $businessId)->where('status', 'joined')->where('created_at', '>=', $since)->count();
    }
}
