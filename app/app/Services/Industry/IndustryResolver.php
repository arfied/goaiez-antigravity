<?php

declare(strict_types=1);

namespace App\Services\Industry;

use App\Enums\IndustryFamily;
use App\Models\Business;
use App\Models\BusinessFact;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;

/**
 * Which industry a business is in, and who said so. The owner's answer on the
 * facts sheet wins over what Google's categories implied; a facts-sheet value
 * that is not one of the six is ignored, never guessed at. `source` is 'owner',
 * 'places' or 'none' — 'none' means the general starting point applies and
 * the screen says so (owner ruling 2026-09-24).
 *
 * @return array{family: ?IndustryFamily, source: string}
 */
final class IndustryResolver
{


    public function for(int $businessId): array
    {
        $ownerRaw = BusinessFact::where('business_id', $businessId)->whereNull('location_id')->where('key', BusinessFactKey::INDUSTRY)->value('value');
        $owner = IndustryFamily::tryFrom((string) ($ownerRaw ?? ''));
        if ($owner !== null) {
            return ['family' => $owner, 'source' => 'owner'];
        }
        $column = Business::query()->whereKey($businessId)->value('industry');
        $derived = $column instanceof IndustryFamily ? $column : IndustryFamily::tryFrom((string) ($column ?? ''));
        if ($derived !== null) {
            return ['family' => $derived, 'source' => 'places'];
        }

        return ['family' => null, 'source' => 'none'];
    }
}
