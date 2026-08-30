<?php

declare(strict_types=1);

namespace App\Modules\X134\Actions;

use App\Modules\X134\Models\EnrichmentField;

final class IdentityResolveAction
{
    /**
     * Merges two records resolving to one identity with field history fully preserved (TEST ANCHOR).
     */
    public function mergeIdentities(int $businessId, string $targetEntityId, string $sourceEntityId): int
    {
        // Re-assign entity_id to target while preserving individual field audit history
        return EnrichmentField::where('business_id', $businessId)
            ->where('entity_id', $sourceEntityId)
            ->update(['entity_id' => $targetEntityId]);
    }
}
