<?php

declare(strict_types=1);

namespace App\Modules\X150\Actions;

use App\Modules\X150\Models\ProviderRoster;

final class ProviderColdAction
{
    public function handle(int $businessId, int $providerId, bool $active): ProviderRoster
    {
        $roster = ProviderRoster::where('business_id', $businessId)->findOrFail($providerId);
        $roster->is_active = $active;
        $roster->save();

        return $roster;
    }
}
