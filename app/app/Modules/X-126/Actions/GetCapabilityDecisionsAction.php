<?php

declare(strict_types=1);

namespace App\Modules\X126\Actions;

use App\Modules\X126\Models\CapabilityDecision;
use Illuminate\Support\Collection;

final class GetCapabilityDecisionsAction
{
    public function handle(int $businessId): Collection
    {
        return CapabilityDecision::where('business_id', $businessId)
            ->where('decision', '!=', 'permitted')
            ->orderBy('id', 'desc')
            ->get();
    }
}
